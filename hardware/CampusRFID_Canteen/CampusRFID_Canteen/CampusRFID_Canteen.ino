#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <SPI.h>
#include <MFRC522.h>
#include <ArduinoJson.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>

#include <Preferences.h>
#include <WebServer.h>
#include <DNSServer.h>

// =====================================================
// FIXED CAMPUS WIFI
// =====================================================

const char* WIFI_SSID = "255.255.255.252";

// =====================================================
// TEMPORARY CAMPUS CANTEEN WIFI SETUP NETWORK
// =====================================================

const char* SETUP_AP_NAME = "SLSU-RFID-CAN-SETUP";
const char* SETUP_AP_PASSWORD = "SLSU2026";

// =====================================================
// API
// =====================================================

const char* API_URL =
  "https://slsubcpatrol.site/api/rfid-scan";

const char* HEARTBEAT_URL =
  "https://slsubcpatrol.site/api/rfid-heartbeat";

// Campus Canteen checkpoint device
#define DEVICE_UID "ESP32-CAN-01"

// =====================================================
// RFID
// =====================================================

#define RFID_SS_PIN 5
#define RFID_RST_PIN 27

// =====================================================
// LCD
// =====================================================

#define LCD_SDA_PIN 21
#define LCD_SCL_PIN 22
#define LCD_COLUMNS 16
#define LCD_ROWS 2

// =====================================================
// BUZZER
// =====================================================

#define BUZZER_PIN 26
#define BUZZER_ON LOW
#define BUZZER_OFF HIGH

// =====================================================
// SETTINGS
// =====================================================

const unsigned long SCAN_COOLDOWN_MS = 3000;
const unsigned long HEARTBEAT_INTERVAL_MS = 60000;
const int WIFI_CONNECT_ATTEMPTS = 40;
const int HTTP_TIMEOUT_MS = 65000;
const int HEARTBEAT_TIMEOUT_MS = 10000;

// =====================================================
// OBJECTS
// =====================================================

LiquidCrystal_I2C lcd(
  0x27,
  LCD_COLUMNS,
  LCD_ROWS
);

MFRC522 rfid(
  RFID_SS_PIN,
  RFID_RST_PIN
);

Preferences preferences;
WebServer server(80);
DNSServer dnsServer;

String lastUid = "";
unsigned long lastScanTime = 0;
unsigned long lastHeartbeatTime = 0;

// =====================================================
// BUZZER
// =====================================================

void buzzerOff() {
  digitalWrite(BUZZER_PIN, BUZZER_OFF);
}

void beep(int times, int onTimeMs, int offTimeMs) {
  for (int i = 0; i < times; i++) {
    digitalWrite(BUZZER_PIN, BUZZER_ON);
    delay(onTimeMs);

    buzzerOff();
    delay(offTimeMs);
  }
}

void successBeep() {
  beep(1, 220, 100);
}

void failedBeep() {
  beep(3, 80, 100);
}

void unknownRfidBeep() {
  beep(4, 70, 90);
}

void readyBeep() {
  beep(2, 60, 80);
}

// =====================================================
// LCD
// =====================================================

String fitLcdText(String text) {
  if (text.length() > LCD_COLUMNS) {
    return text.substring(0, LCD_COLUMNS);
  }

  while (text.length() < LCD_COLUMNS) {
    text += " ";
  }

  return text;
}

void lcdMessage(String line1, String line2 = "") {
  lcd.clear();

  lcd.setCursor(0, 0);
  lcd.print(fitLcdText(line1));

  lcd.setCursor(0, 1);
  lcd.print(fitLcdText(line2));
}

void showReady() {
  lcdMessage("RFID Ready", "Scan Card");
  readyBeep();
}

void finishScanScreen(int waitMs = 2500) {
  delay(waitMs);
  showReady();
}

// =====================================================
// WIFI PASSWORD STORAGE
// =====================================================

String getSavedWiFiPassword() {
  preferences.begin(
    "wifi-config",
    true
  );

  String password =
    preferences.getString(
      "password",
      ""
    );

  preferences.end();

  return password;
}

void saveWiFiPassword(String password) {
  preferences.begin(
    "wifi-config",
    false
  );

  preferences.putString(
    "password",
    password
  );

  preferences.end();

  Serial.println(
    "New WiFi password saved."
  );
}

// =====================================================
// WIFI SETUP PAGE
// =====================================================

String setupPage() {

  String html = R"rawliteral(

<!DOCTYPE html>

<html>

<head>

<meta name="viewport"
content="width=device-width, initial-scale=1">

<title>SLSU RFID WiFi Setup</title>

<style>

body {
  font-family: Arial, sans-serif;
  background: #f4f7fb;
  margin: 0;
  padding: 25px;
}

.container {
  max-width: 420px;
  margin: auto;
  background: white;
  padding: 25px;
  border-radius: 15px;
  box-shadow: 0px 4px 15px rgba(0,0,0,0.15);
}

h2 {
  color: #123f73;
  text-align: center;
}

.info {
  background: #eef5ff;
  padding: 12px;
  border-radius: 8px;
  margin-bottom: 20px;
}

label {
  font-weight: bold;
}

input[type=password] {
  width: 100%;
  padding: 13px;
  margin-top: 8px;
  margin-bottom: 18px;
  border: 1px solid #ccc;
  border-radius: 8px;
  box-sizing: border-box;
  font-size: 16px;
}

button {
  width: 100%;
  padding: 14px;
  background: #123f73;
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 16px;
}

button:hover {
  background: #0b315b;
}

.small {
  text-align: center;
  font-size: 13px;
  color: #666;
  margin-top: 15px;
}

</style>

</head>

<body>

<div class="container">

<h2>SLSU RFID WiFi Setup</h2>

<div class="info">

<strong>Campus WiFi:</strong><br>

255.255.255.252

</div>

<form action="/save" method="POST">

<label>
Enter New WiFi Password:
</label>

<input
type="password"
name="password"
required
placeholder="Enter campus WiFi password">

<button type="submit">
Save Password
</button>

</form>

<div class="small">

Campus Canteen RFID Checkpoint

</div>

</div>

</body>

</html>

)rawliteral";

  return html;
}

// =====================================================
// START WIFI SETUP PORTAL
// =====================================================

void startWiFiSetupPortal() {

  Serial.println();

  Serial.println(
    "============================="
  );

  Serial.println(
    "STARTING CANTEEN WIFI SETUP MODE"
  );

  Serial.println(
    "============================="
  );

  lcdMessage(
    "WiFi Setup Mode",
    "Use Phone"
  );

  failedBeep();

  WiFi.disconnect(true);

  delay(1000);

  WiFi.mode(WIFI_AP);

  WiFi.softAP(
    SETUP_AP_NAME,
    SETUP_AP_PASSWORD
  );

  IPAddress apIP =
    WiFi.softAPIP();

  Serial.print(
    "Setup WiFi: "
  );

  Serial.println(
    SETUP_AP_NAME
  );

  Serial.print(
    "Setup Password: "
  );

  Serial.println(
    SETUP_AP_PASSWORD
  );

  Serial.print(
    "Setup IP: "
  );

  Serial.println(
    apIP
  );

  lcdMessage(
    "CAN WiFi Setup",
    "Use Phone"
  );

  // =================================================
  // DNS
  // =================================================

  dnsServer.start(
    53,
    "*",
    apIP
  );

  // =================================================
  // MAIN PAGE
  // =================================================

  server.on(
    "/",
    HTTP_GET,
    []() {

      server.send(
        200,
        "text/html",
        setupPage()
      );
    }
  );

  // =================================================
  // SAVE PASSWORD
  // =================================================

  server.on(
    "/save",
    HTTP_POST,
    []() {

      if (
        server.hasArg("password")
      ) {

        String newPassword =
          server.arg("password");

        newPassword.trim();

        if (
          newPassword.length() >= 8
        ) {

          saveWiFiPassword(
            newPassword
          );

          String successPage = R"rawliteral(

<!DOCTYPE html>

<html>

<head>

<meta name="viewport"
content="width=device-width, initial-scale=1">

<title>Password Saved</title>

<style>

body {
  font-family: Arial;
  text-align: center;
  padding: 40px;
  background: #f4f7fb;
}

.box {
  max-width: 400px;
  margin: auto;
  background: white;
  padding: 30px;
  border-radius: 15px;
}

h2 {
  color: #123f73;
}

</style>

</head>

<body>

<div class="box">

<h2>Password Saved</h2>

<p>
The new campus WiFi password has been saved.
</p>

<p>
The Campus Canteen RFID device will restart automatically.
</p>

</div>

</body>

</html>

)rawliteral";

          server.send(
            200,
            "text/html",
            successPage
          );

          lcdMessage(
            "Password Saved",
            "Restarting..."
          );

          successBeep();

          delay(3000);

          ESP.restart();
        }

        else {

          server.send(
            400,
            "text/plain",
            "Password must contain at least 8 characters."
          );
        }
      }

      else {

        server.send(
          400,
          "text/plain",
          "Password missing."
        );
      }
    }
  );

  server.onNotFound(
    []() {

      server.sendHeader(
        "Location",
        "/"
      );

      server.send(
        302,
        "text/plain",
        ""
      );
    }
  );

  server.begin();

  Serial.println(
    "Campus Canteen WiFi setup portal started."
  );

  Serial.println(
    "Connect phone to:"
  );

  Serial.println(
    SETUP_AP_NAME
  );

  Serial.println(
    "Then open:"
  );

  Serial.println(
    "192.168.4.1"
  );

  while (true) {

    dnsServer.processNextRequest();

    server.handleClient();

    delay(2);
  }
}

// =====================================================
// CONNECT WIFI
// =====================================================

void connectWiFi() {

  Serial.println();

  Serial.println(
    "Connecting Campus Canteen device to campus WiFi..."
  );

  lcdMessage(
    "Connecting WiFi",
    "Please wait..."
  );

  String savedPassword =
    getSavedWiFiPassword();

  // No saved password yet
  if (
    savedPassword.length() == 0
  ) {

    Serial.println(
      "No saved WiFi password."
    );

    startWiFiSetupPortal();

    return;
  }

  // Connect using saved password
  WiFi.mode(WIFI_STA);

  WiFi.setSleep(false);

  WiFi.begin(
    WIFI_SSID,
    savedPassword.c_str()
  );

  int attempts = 0;

  while (
    WiFi.status() != WL_CONNECTED &&
    attempts < WIFI_CONNECT_ATTEMPTS
  ) {

    delay(500);

    Serial.print(".");

    attempts++;
  }

  // Connected
  if (
    WiFi.status() == WL_CONNECTED
  ) {

    Serial.println();

    Serial.println(
      "WiFi Connected"
    );

    Serial.print(
      "SSID: "
    );

    Serial.println(
      WiFi.SSID()
    );

    Serial.print(
      "IP Address: "
    );

    Serial.println(
      WiFi.localIP()
    );

    Serial.print(
      "Device: "
    );

    Serial.println(
      DEVICE_UID
    );

    Serial.print(
      "API URL: "
    );

    Serial.println(
      API_URL
    );

    lcdMessage(
      "WiFi Connected",
      WiFi.localIP().toString()
    );

    successBeep();

    delay(1500);

    showReady();
  }

  // Saved password no longer works
  else {

    Serial.println();

    Serial.println(
      "Saved password failed."
    );

    Serial.println(
      "Starting Campus Canteen setup portal..."
    );

    lcdMessage(
      "WiFi Changed?",
      "Setup Required"
    );

    delay(2000);

    startWiFiSetupPortal();
  }
}

// =====================================================
// SEND READER HEARTBEAT TO LARAVEL
// =====================================================

void sendHeartbeatToLaravel() {

  if (
    WiFi.status() != WL_CONNECTED
  ) {

    Serial.println(
      "Heartbeat skipped. WiFi disconnected."
    );

    return;
  }

  String heartbeatEndpoint =
    String(HEARTBEAT_URL) +
    "?device_uid=" +
    DEVICE_UID;

  Serial.println(
    "---------------------"
  );

  Serial.println(
    "Sending reader heartbeat..."
  );

  Serial.print(
    "Device: "
  );

  Serial.println(
    DEVICE_UID
  );

  Serial.print(
    "Heartbeat URL: "
  );

  Serial.println(
    heartbeatEndpoint
  );

  WiFiClientSecure client;

  client.setInsecure();

  HTTPClient http;

  if (
    !http.begin(
      client,
      heartbeatEndpoint
    )
  ) {

    Serial.println(
      "Invalid heartbeat URL"
    );

    return;
  }

  http.setTimeout(
    HEARTBEAT_TIMEOUT_MS
  );

  http.addHeader(
    "Accept",
    "application/json"
  );

  http.addHeader(
    "User-Agent",
    "ESP32-RFID-Heartbeat/1.0"
  );

  int httpCode =
    http.GET();

  String response =
    http.getString();

  Serial.print(
    "Heartbeat HTTP Code: "
  );

  Serial.println(
    httpCode
  );

  if (
    httpCode <= 0
  ) {

    Serial.print(
      "Heartbeat Error: "
    );

    Serial.println(
      http.errorToString(
        httpCode
      )
    );
  }

  else {

    Serial.println(
      "Heartbeat Response:"
    );

    Serial.println(
      response
    );
  }

  http.end();
}

void handleHeartbeat() {

  if (
    millis() -
    lastHeartbeatTime <
    HEARTBEAT_INTERVAL_MS
  ) {

    return;
  }

  lastHeartbeatTime =
    millis();

  sendHeartbeatToLaravel();
}

// =====================================================
// UNKNOWN RFID RESPONSE
// =====================================================

bool isUnknownRfidResponse(String response) {

  StaticJsonDocument<1024> doc;

  DeserializationError error =
    deserializeJson(
      doc,
      response
    );

  if (error) {
    return false;
  }

  bool guardMissing =
    doc["guard"].isNull();

  String message =
    doc["message"] | "";

  String diagnostic =
    doc["diagnostic"] | "";

  String status =
    doc["status"] | "";

  String text =
    message + " " +
    diagnostic + " " +
    status;

  text.toLowerCase();

  bool mentionsUnknown =
    text.indexOf("unknown") >= 0 ||
    text.indexOf("unregistered") >= 0;

  bool mentionsCardOrGuard =
    text.indexOf("card") >= 0 ||
    text.indexOf("rfid") >= 0 ||
    text.indexOf("guard") >= 0;

  bool mentionsReaderOrCheckpoint =
    text.indexOf("reader") >= 0 ||
    text.indexOf("checkpoint") >= 0 ||
    text.indexOf("device") >= 0;

  return
    guardMissing ||
    mentionsUnknown ||
    (
      mentionsCardOrGuard &&
      !mentionsReaderOrCheckpoint
    );
}

// =====================================================
// SHOW GUARD NAME
// =====================================================

void showGuardName(String guardName) {

  lcdMessage(
    "RFID ACCEPTED",
    guardName
  );

  if (
    guardName.length() > LCD_COLUMNS
  ) {

    delay(800);

    for (
      int i = 0;
      i <= guardName.length() - LCD_COLUMNS;
      i++
    ) {

      lcd.setCursor(
        0,
        1
      );

      lcd.print(
        guardName.substring(
          i,
          i + LCD_COLUMNS
        )
      );

      delay(300);
    }
  }

  delay(2000);

  lcdMessage(
    "Patrol Logged",
    "Thank You"
  );
}

// =====================================================
// GET CARD UID
// =====================================================

String getCardUid() {

  String uid = "";

  for (
    byte i = 0;
    i < rfid.uid.size;
    i++
  ) {

    if (
      rfid.uid.uidByte[i] < 0x10
    ) {

      uid += "0";
    }

    uid += String(
      rfid.uid.uidByte[i],
      HEX
    );
  }

  uid.toUpperCase();

  return uid;
}

// =====================================================
// SEND SCAN TO LARAVEL
// =====================================================

void sendScanToLaravel(String rfidUid) {

  if (
    WiFi.status() != WL_CONNECTED
  ) {

    Serial.println(
      "WiFi disconnected"
    );

    lcdMessage(
      "WiFi Lost",
      "Reconnecting..."
    );

    connectWiFi();

    if (
      WiFi.status() != WL_CONNECTED
    ) {

      Serial.println(
        "Cannot send scan. WiFi still disconnected."
      );

      failedBeep();

      lcdMessage(
        "Send Failed",
        "No WiFi"
      );

      finishScanScreen();

      return;
    }
  }

  Serial.println(
    "---------------------"
  );

  Serial.println(
    "Sending RFID Scan..."
  );

  Serial.print(
    "UID: "
  );

  Serial.println(
    rfidUid
  );

  Serial.print(
    "Device: "
  );

  Serial.println(
    DEVICE_UID
  );

  Serial.print(
    "API URL: "
  );

  Serial.println(
    API_URL
  );

  lcdMessage(
    "Sending Scan",
    "Wait response"
  );

  WiFiClientSecure client;

  client.setInsecure();

  HTTPClient http;

  if (
    !http.begin(
      client,
      API_URL
    )
  ) {

    Serial.println(
      "Invalid API URL"
    );

    failedBeep();

    lcdMessage(
      "Invalid URL",
      "Check API"
    );

    finishScanScreen();

    return;
  }

  http.setTimeout(
    HTTP_TIMEOUT_MS
  );

  http.addHeader(
    "Content-Type",
    "application/json"
  );

  http.addHeader(
    "Accept",
    "application/json"
  );

  http.addHeader(
    "User-Agent",
    "ESP32-RFID-Reader/1.0"
  );

  StaticJsonDocument<256> payload;

  payload["rfid_uid"] =
    rfidUid;

  payload["device_uid"] =
    DEVICE_UID;

  String requestBody;

  serializeJson(
    payload,
    requestBody
  );

  Serial.print(
    "Request Body: "
  );

  Serial.println(
    requestBody
  );

  int httpCode =
    http.POST(
      requestBody
    );

  String response =
    http.getString();

  Serial.print(
    "HTTP Code: "
  );

  Serial.println(
    httpCode
  );

  if (
    httpCode <= 0
  ) {

    Serial.print(
      "HTTP Error: "
    );

    Serial.println(
      http.errorToString(
        httpCode
      )
    );
  }

  Serial.println(
    "Server Response:"
  );

  Serial.println(
    response
  );

  if (
    httpCode == 201 ||
    httpCode == 200
  ) {

    StaticJsonDocument<1024> doc;

    DeserializationError error =
      deserializeJson(
        doc,
        response
      );

    successBeep();

    if (!error) {

      const char* status =
        doc["status"] | "accepted";

      const char* guardNameC =
        doc["guard"]["name"] | "Guard Found";

      String guardName =
        String(guardNameC);

      Serial.println(
        "RFID ACCEPTED"
      );

      Serial.print(
        "Guard: "
      );

      Serial.println(
        guardName
      );

      Serial.print(
        "Status: "
      );

      Serial.println(
        status
      );

      Serial.println(
        "Patrol logged"
      );

      showGuardName(
        guardName
      );
    }

    else {

      Serial.println(
        "RFID Accepted"
      );

      Serial.println(
        "Patrol logged"
      );

      lcdMessage(
        "Patrol Logged",
        "Thank You"
      );
    }
  }

  else if (
    httpCode == 409
  ) {

    Serial.println(
      "Scan denied by server"
    );

    failedBeep();

    lcdMessage(
      "Scan Denied",
      "Contact Admin"
    );
  }

  else if (
    httpCode == 422
  ) {

    Serial.println(
      "Invalid RFID Scan"
    );

    Serial.println(
      "Check guard/card/checkpoint"
    );

    if (
      isUnknownRfidResponse(
        response
      )
    ) {

      unknownRfidBeep();

      lcdMessage(
        "Unknown RFID",
        "Not Registered"
      );
    }

    else {

      failedBeep();

      lcdMessage(
        "Invalid Scan",
        "Check Device"
      );
    }
  }

  else if (
    httpCode == 404
  ) {

    Serial.println(
      "RFID not found"
    );

    unknownRfidBeep();

    lcdMessage(
      "Unknown RFID",
      "Not Registered"
    );
  }

  else if (
    httpCode <= 0
  ) {

    Serial.println(
      "No Server Reply"
    );

    Serial.println(
      "Check API/WiFi"
    );

    failedBeep();

    lcdMessage(
      "No Server Reply",
      "Check API/WiFi"
    );
  }

  else {

    Serial.print(
      "Server Error: "
    );

    Serial.println(
      httpCode
    );

    failedBeep();

    lcdMessage(
      "Server Error",
      String(httpCode)
    );
  }

  http.end();

  finishScanScreen();
}

// =====================================================
// SETUP
// =====================================================

void setup() {

  Serial.begin(115200);

  delay(1000);

  pinMode(
    BUZZER_PIN,
    OUTPUT
  );

  buzzerOff();

  Wire.begin(
    LCD_SDA_PIN,
    LCD_SCL_PIN
  );

  lcd.init();

  lcd.backlight();

  lcdMessage(
    "Campus RFID",
    "Starting..."
  );

  Serial.println(
    "Campus RFID Patrol System"
  );

  Serial.println(
    "Campus Canteen Checkpoint"
  );

  SPI.begin();

  rfid.PCD_Init();

  Serial.println(
    "RFID Reader Ready"
  );

  connectWiFi();

  sendHeartbeatToLaravel();

  lastHeartbeatTime =
    millis();

  Serial.println(
    "Ready to Scan RFID Card"
  );
}

// =====================================================
// LOOP
// =====================================================

void loop() {

  handleHeartbeat();

  if (
    !rfid.PICC_IsNewCardPresent()
  ) {

    return;
  }

  if (
    !rfid.PICC_ReadCardSerial()
  ) {

    return;
  }

  String uid =
    getCardUid();

  Serial.print(
    "Scanned UID: "
  );

  Serial.println(
    uid
  );

  lcdMessage(
    "Card Detected",
    uid
  );

  if (
    uid == lastUid &&
    millis() -
    lastScanTime <
    SCAN_COOLDOWN_MS
  ) {

    Serial.println(
      "Duplicate Scan"
    );

    lcdMessage(
      "Duplicate Scan",
      "Please wait"
    );

    rfid.PICC_HaltA();

    rfid.PCD_StopCrypto1();

    delay(1000);

    showReady();

    return;
  }

  lastUid =
    uid;

  lastScanTime =
    millis();

  Serial.println(
    "Processing RFID..."
  );

  lcdMessage(
    "Processing",
    "Please wait..."
  );

  sendScanToLaravel(
    uid
  );

  rfid.PICC_HaltA();

  rfid.PCD_StopCrypto1();

  Serial.println(
    "---------------------"
  );

  Serial.println(
    "Ready for next scan"
  );
}
