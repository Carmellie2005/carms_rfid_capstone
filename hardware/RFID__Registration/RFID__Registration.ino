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

// ======================================================
// FIXED CAMPUS WIFI
// ======================================================

const char* WIFI_SSID = "255.255.255.252";

// ======================================================
// TEMPORARY REGISTRATION WIFI SETUP NETWORK
// ======================================================

const char* SETUP_AP_NAME = "SLSU-RFID-REG-SETUP";
const char* SETUP_AP_PASSWORD = "SLSU2026";

// ======================================================
// RFID ENROLLMENT API
// ======================================================

const char* API_URL =
  "https://slsubcpatrol.site/api/rfid-enrollment";

#define DEVICE_UID "ESP32-REG-01";

// ======================================================
// RFID PINS
// ======================================================

#define RFID_SS_PIN 5
#define RFID_RST_PIN 27

// ======================================================
// LCD
// ======================================================

#define LCD_SDA_PIN 21
#define LCD_SCL_PIN 22

#define LCD_COLUMNS 16
#define LCD_ROWS 2

// ======================================================
// ACTIVE BUZZER
// ======================================================

#define BUZZER_PIN 26

#define BUZZER_ON LOW
#define BUZZER_OFF HIGH

// ======================================================
// SETTINGS
// ======================================================

const unsigned long SCAN_COOLDOWN_MS = 2500;

const int WIFI_CONNECT_ATTEMPTS = 40;

const int HTTP_TIMEOUT_MS = 15000;

// ======================================================
// OBJECTS
// ======================================================

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

// ======================================================
// GLOBAL VARIABLES
// ======================================================

String lastUid = "";

unsigned long lastScanTime = 0;

// ======================================================
// BUZZER
// ======================================================

void buzzerOff() {
  digitalWrite(
    BUZZER_PIN,
    BUZZER_OFF
  );
}

void beep(
  int times,
  int onTimeMs,
  int offTimeMs
) {

  for (int i = 0; i < times; i++) {

    digitalWrite(
      BUZZER_PIN,
      BUZZER_ON
    );

    delay(onTimeMs);

    buzzerOff();

    delay(offTimeMs);
  }
}

void readyBeep() {
  beep(2, 60, 80);
}

void successBeep() {
  beep(1, 220, 100);
}

void errorBeep() {
  beep(3, 80, 100);
}

void duplicateBeep() {
  beep(2, 120, 150);
}

void wifiConnectedBeep() {
  beep(2, 70, 70);
}

void wifiFailedBeep() {
  beep(3, 150, 120);
}

// ======================================================
// LCD
// ======================================================

String fitLcdText(String text) {

  if (
    text.length() >
    LCD_COLUMNS
  ) {

    return text.substring(
      0,
      LCD_COLUMNS
    );
  }

  while (
    text.length() <
    LCD_COLUMNS
  ) {

    text += " ";
  }

  return text;
}

void lcdMessage(
  String line1,
  String line2 = ""
) {

  lcd.clear();

  lcd.setCursor(
    0,
    0
  );

  lcd.print(
    fitLcdText(
      line1
    )
  );

  lcd.setCursor(
    0,
    1
  );

  lcd.print(
    fitLcdText(
      line2
    )
  );
}

// ======================================================
// READY SCREEN
// ======================================================

void showReady(
  bool playSound = true
) {

  Serial.println(
    "Ready. Click Scan Card in Guard Management, then tap RFID card."
  );

  lcdMessage(
    "Enroll Ready",
    "Tap RFID Card"
  );

  if (playSound) {
    readyBeep();
  }
}

void finishScanScreen(
  int waitMs = 2200
) {

  delay(waitMs);

  showReady(false);
}

// ======================================================
// WIFI PASSWORD STORAGE
// ======================================================

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

void saveWiFiPassword(
  String password
) {

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

// ======================================================
// WIFI SETUP PAGE
// ======================================================

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

RFID Registration Reader

</div>

</div>

</body>

</html>

)rawliteral";

  return html;
}

// ======================================================
// START WIFI CONFIGURATION PORTAL
// ======================================================

void startWiFiSetupPortal() {

  Serial.println();

  Serial.println(
    "============================="
  );

  Serial.println(
    "STARTING REGISTRATION WIFI SETUP"
  );

  Serial.println(
    "============================="
  );

  lcdMessage(
    "WiFi Setup Mode",
    "Use Phone"
  );

  wifiFailedBeep();

  WiFi.disconnect(true);

  delay(1000);

  // ESP32 creates temporary WiFi
  WiFi.mode(
    WIFI_AP
  );

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
    "REG WiFi Setup",
    "Use Phone"
  );

  // ==================================================
  // DNS
  // ==================================================

  dnsServer.start(
    53,
    "*",
    apIP
  );

  // ==================================================
  // MAIN PAGE
  // ==================================================

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

  // ==================================================
  // SAVE PASSWORD
  // ==================================================

  server.on(
    "/save",
    HTTP_POST,
    []() {

      if (
        server.hasArg(
          "password"
        )
      ) {

        String newPassword =
          server.arg(
            "password"
          );

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
The RFID registration reader will restart automatically.
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
    "Registration WiFi setup portal started."
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

// ======================================================
// WIFI CONNECTION
// ======================================================

void connectWiFi() {

  Serial.println();

  Serial.println(
    "Connecting RFID registration reader..."
  );

  lcdMessage(
    "Connecting WiFi",
    "Please wait..."
  );

  String savedPassword =
    getSavedWiFiPassword();

  // ==================================================
  // NO SAVED PASSWORD
  // ==================================================

  if (
    savedPassword.length() == 0
  ) {

    Serial.println(
      "No saved WiFi password."
    );

    startWiFiSetupPortal();

    return;
  }

  // ==================================================
  // CONNECT USING SAVED PASSWORD
  // ==================================================

  WiFi.mode(
    WIFI_STA
  );

  WiFi.setSleep(
    false
  );

  WiFi.begin(
    WIFI_SSID,
    savedPassword.c_str()
  );

  int attempts = 0;

  while (
    WiFi.status() != WL_CONNECTED &&
    attempts <
    WIFI_CONNECT_ATTEMPTS
  ) {

    delay(500);

    Serial.print(".");

    attempts++;
  }

  Serial.println();

  // ==================================================
  // CONNECTED
  // ==================================================

  if (
    WiFi.status() ==
    WL_CONNECTED
  ) {

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

    wifiConnectedBeep();

    lcdMessage(
      "WiFi Connected",
      WiFi.localIP().toString()
    );

    delay(1500);

    showReady(true);
  }

  // ==================================================
  // SAVED PASSWORD FAILED
  // ==================================================

  else {

    Serial.println(
      "Saved WiFi password failed."
    );

    Serial.println(
      "Starting registration WiFi setup portal..."
    );

    lcdMessage(
      "WiFi Changed?",
      "Setup Required"
    );

    delay(2000);

    startWiFiSetupPortal();
  }
}

// ======================================================
// GET RFID UID
// ======================================================

String getCardUid() {

  String uid = "";

  for (
    byte i = 0;
    i < rfid.uid.size;
    i++
  ) {

    if (
      rfid.uid.uidByte[i] <
      0x10
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

// ======================================================
// SEND RFID ENROLLMENT
// ======================================================

void sendEnrollmentToLaravel(
  String rfidUid
) {

  // ==================================================
  // CHECK WIFI
  // ==================================================

  if (
    WiFi.status() !=
    WL_CONNECTED
  ) {

    Serial.println(
      "WiFi disconnected. Reconnecting..."
    );

    lcdMessage(
      "WiFi Lost",
      "Reconnecting"
    );

    connectWiFi();

    if (
      WiFi.status() !=
      WL_CONNECTED
    ) {

      Serial.println(
        "Cannot send UID."
      );

      errorBeep();

      lcdMessage(
        "Send Failed",
        "No WiFi"
      );

      finishScanScreen();

      return;
    }
  }

  // ==================================================
  // ENROLLMENT INFORMATION
  // ==================================================

  Serial.println();

  Serial.println(
    "---------------------"
  );

  Serial.println(
    "Sending RFID Enrollment..."
  );

  Serial.print(
    "RFID UID: "
  );

  Serial.println(
    rfidUid
  );

  Serial.print(
    "Device UID: "
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
    "Sending UID",
    rfidUid
  );

  // ==================================================
  // HTTPS
  // ==================================================

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

    errorBeep();

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
    "ESP32-RFID-Enrollment/1.0"
  );

  // ==================================================
  // PAYLOAD
  // ==================================================

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

  // ==================================================
  // POST
  // ==================================================

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

  http.end();

  // ==================================================
  // SUCCESS
  // ==================================================

  if (
    httpCode == 200 ||
    httpCode == 201
  ) {

    successBeep();

    lcdMessage(
      "Card Captured",
      rfidUid
    );

    Serial.println(
      "Success. UID should appear in Guard Management."
    );
  }

  // ==================================================
  // INVALID UID
  // ==================================================

  else if (
    httpCode == 422
  ) {

    errorBeep();

    lcdMessage(
      "Invalid UID",
      "Check card"
    );

    Serial.println(
      "Invalid enrollment payload."
    );
  }

  // ==================================================
  // SERVER ERROR
  // ==================================================

  else if (
    httpCode > 0
  ) {

    errorBeep();

    lcdMessage(
      "Server Error",
      String(httpCode)
    );

    Serial.println(
      "Server returned an error."
    );
  }

  // ==================================================
  // NO RESPONSE
  // ==================================================

  else {

    errorBeep();

    lcdMessage(
      "No Server Reply",
      "Check Internet"
    );

    Serial.println(
      "No server reply."
    );
  }

  finishScanScreen();
}

// ======================================================
// SETUP
// ======================================================

void setup() {

  Serial.begin(
    115200
  );

  delay(
    1800
  );

  // ACTIVE BUZZER

  pinMode(
    BUZZER_PIN,
    OUTPUT
  );

  buzzerOff();

  // LCD

  Wire.begin(
    LCD_SDA_PIN,
    LCD_SCL_PIN
  );

  lcd.init();

  lcd.backlight();

  lcdMessage(
    "RFID Enroller",
    "Starting..."
  );

  Serial.println();

  Serial.println(
    "=============================="
  );

  Serial.println(
    "RFID REGISTRATION READER BOOT"
  );

  Serial.println(
    "=============================="
  );

  Serial.println(
    "Purpose: Guard card enrollment"
  );

  Serial.print(
    "Device UID: "
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

  // RFID

  SPI.begin();

  rfid.PCD_Init();

  byte version =
    rfid.PCD_ReadRegister(
      MFRC522::VersionReg
    );

  Serial.print(
    "MFRC522 Version: 0x"
  );

  Serial.println(
    version,
    HEX
  );

  if (
    version == 0x00 ||
    version == 0xFF
  ) {

    Serial.println(
      "Warning: RFID reader not detected."
    );

    errorBeep();

    lcdMessage(
      "RFID Warning",
      "Check wiring"
    );

    delay(2000);
  }

  // WIFI

  connectWiFi();
}

// ======================================================
// LOOP
// ======================================================

void loop() {

  // ==================================================
  // WIFI CHECK
  // ==================================================

  if (
    WiFi.status() !=
    WL_CONNECTED
  ) {

    connectWiFi();

    delay(1000);

    return;
  }

  // ==================================================
  // WAIT FOR CARD
  // ==================================================

  if (
    !rfid.PICC_IsNewCardPresent()
  ) {

    delay(50);

    return;
  }

  if (
    !rfid.PICC_ReadCardSerial()
  ) {

    delay(50);

    return;
  }

  // ==================================================
  // READ UID
  // ==================================================

  String uid =
    getCardUid();

  unsigned long nowMs =
    millis();

  Serial.println();

  Serial.print(
    "Card Detected: "
  );

  Serial.println(
    uid
  );

  lcdMessage(
    "Card Detected",
    uid
  );

  // ==================================================
  // DUPLICATE CHECK
  // ==================================================

  if (
    uid == lastUid &&
    nowMs -
    lastScanTime <
    SCAN_COOLDOWN_MS
  ) {

    Serial.println(
      "Duplicate card ignored."
    );

    duplicateBeep();

    lcdMessage(
      "Duplicate Scan",
      "Please wait"
    );

    rfid.PICC_HaltA();

    rfid.PCD_StopCrypto1();

    delay(1000);

    showReady(false);

    return;
  }

  // ==================================================
  // SAVE SCAN
  // ==================================================

  lastUid =
    uid;

  lastScanTime =
    nowMs;

  // ==================================================
  // SEND TO LARAVEL
  // ==================================================

  sendEnrollmentToLaravel(
    uid
  );

  // ==================================================
  // CLOSE RFID SESSION
  // ==================================================

  rfid.PICC_HaltA();

  rfid.PCD_StopCrypto1();

  Serial.println(
    "---------------------"
  );

  Serial.println(
    "Ready for next card."
  );
}