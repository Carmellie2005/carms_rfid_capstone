#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <SPI.h>
#include <MFRC522.h>
#include <ArduinoJson.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>

// Change these when the device is deployed to another WiFi or hotspot.
const char* WIFI_SSID = "carmspisowifi";
const char* WIFI_PASSWORD = "87654321";

// Render-hosted Laravel API URL.
const char* API_URL = "https://carms-rfid-capstone.onrender.com/api/rfid-scan";

// IT checkpoint reader device ID. Change this per checkpoint reader:
// IT = ESP32-IT-01, MPC = ESP32-MPC-01, FI = ESP32-FI-01,
// Campus Canteen = ESP32-CAN-01, AG = ESP32-AG-01.
#define DEVICE_UID "ESP32-IT-01"

// MFRC522 RFID pins.
#define RFID_SS_PIN 5
#define RFID_RST_PIN 27

// LCD I2C settings.
#define LCD_SDA_PIN 21
#define LCD_SCL_PIN 22
#define LCD_COLUMNS 16
#define LCD_ROWS 2

// Two-pin buzzer: + to GPIO 26, - to GND.
#define BUZZER_PIN 26

const unsigned long SCAN_COOLDOWN_MS = 3000;
const int WIFI_CONNECT_ATTEMPTS = 40;
const int HTTP_TIMEOUT_MS = 65000;

LiquidCrystal_I2C lcd(0x27, LCD_COLUMNS, LCD_ROWS);
MFRC522 rfid(RFID_SS_PIN, RFID_RST_PIN);

String lastUid = "";
unsigned long lastScanTime = 0;

void playTone(int frequency, int durationMs) {
  int halfPeriodUs = 1000000L / frequency / 2;
  long cycles = (long) frequency * durationMs / 1000;

  for (long i = 0; i < cycles; i++) {
    digitalWrite(BUZZER_PIN, HIGH);
    delayMicroseconds(halfPeriodUs);
    digitalWrite(BUZZER_PIN, LOW);
    delayMicroseconds(halfPeriodUs);
  }

  digitalWrite(BUZZER_PIN, LOW);
}

void successBeep() {
  playTone(2200, 150);
  delay(80);
}

void failedBeep() {
  playTone(900, 120);
  delay(90);
  playTone(900, 120);
  delay(80);
}

void unknownRfidBeep() {
  playTone(650, 80);
  delay(70);
  playTone(650, 80);
  delay(70);
  playTone(650, 80);
  delay(80);
}

void readyBeep() {
  playTone(1800, 60);
  delay(60);
  playTone(2400, 60);
  delay(80);
}

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

bool isUnknownRfidResponse(String response) {
  StaticJsonDocument<1024> doc;
  DeserializationError error = deserializeJson(doc, response);

  if (error) {
    return false;
  }

  bool guardMissing = doc["guard"].isNull();

  String message = doc["message"] | "";
  String diagnostic = doc["diagnostic"] | "";
  String status = doc["status"] | "";

  String text = message + " " + diagnostic + " " + status;
  text.toLowerCase();

  bool mentionsUnknown = text.indexOf("unknown") >= 0 || text.indexOf("unregistered") >= 0;
  bool mentionsCardOrGuard = text.indexOf("card") >= 0 || text.indexOf("rfid") >= 0 || text.indexOf("guard") >= 0;
  bool mentionsReaderOrCheckpoint = text.indexOf("reader") >= 0 || text.indexOf("checkpoint") >= 0 || text.indexOf("device") >= 0;

  return guardMissing || mentionsUnknown || (mentionsCardOrGuard && !mentionsReaderOrCheckpoint);
}

void showGuardName(String guardName) {
  lcdMessage("RFID ACCEPTED", guardName);

  if (guardName.length() > LCD_COLUMNS) {
    delay(800);

    for (int i = 0; i <= guardName.length() - LCD_COLUMNS; i++) {
      lcd.setCursor(0, 1);
      lcd.print(guardName.substring(i, i + LCD_COLUMNS));
      delay(300);
    }
  }

  delay(2000);
  lcdMessage("Face Verify", "Use Web System");
}

void connectWiFi() {
  Serial.println("Connecting WiFi...");
  lcdMessage("Connecting WiFi", "Please wait...");

  WiFi.mode(WIFI_STA);
  WiFi.setSleep(false);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  int attempts = 0;

  while (WiFi.status() != WL_CONNECTED && attempts < WIFI_CONNECT_ATTEMPTS) {
    delay(500);
    Serial.print(".");
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println();
    Serial.println("WiFi Connected");
    Serial.print("IP Address: ");
    Serial.println(WiFi.localIP());

    lcdMessage("WiFi Connected", WiFi.localIP().toString());
    delay(1500);

    Serial.print("Device: ");
    Serial.println(DEVICE_UID);
    Serial.print("API URL: ");
    Serial.println(API_URL);

    showReady();
  } else {
    Serial.println();
    Serial.println("WiFi Failed");

    failedBeep();
    lcdMessage("WiFi Failed", "Check Network");
  }
}

String getCardUid() {
  String uid = "";

  for (byte i = 0; i < rfid.uid.size; i++) {
    if (rfid.uid.uidByte[i] < 0x10) {
      uid += "0";
    }

    uid += String(rfid.uid.uidByte[i], HEX);
  }

  uid.toUpperCase();
  return uid;
}

void sendScanToLaravel(String rfidUid) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi disconnected");
    lcdMessage("WiFi Lost", "Reconnecting...");

    connectWiFi();

    if (WiFi.status() != WL_CONNECTED) {
      Serial.println("Cannot send scan. WiFi still disconnected.");

      failedBeep();
      lcdMessage("Send Failed", "No WiFi");
      finishScanScreen();
      return;
    }
  }

  Serial.println("---------------------");
  Serial.println("Sending RFID Scan...");
  Serial.print("UID: ");
  Serial.println(rfidUid);
  Serial.print("Device: ");
  Serial.println(DEVICE_UID);
  Serial.print("API URL: ");
  Serial.println(API_URL);

  lcdMessage("Sending Scan", "Wait response");

  WiFiClientSecure client;
  client.setInsecure();

  HTTPClient http;

  if (! http.begin(client, API_URL)) {
    Serial.println("Invalid API URL");

    failedBeep();
    lcdMessage("Invalid URL", "Check API");
    finishScanScreen();
    return;
  }

  http.setTimeout(HTTP_TIMEOUT_MS);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.addHeader("User-Agent", "ESP32-RFID-Reader/1.0");

  StaticJsonDocument<256> payload;
  payload["rfid_uid"] = rfidUid;
  payload["device_uid"] = DEVICE_UID;

  String requestBody;
  serializeJson(payload, requestBody);

  Serial.print("Request Body: ");
  Serial.println(requestBody);

  int httpCode = http.POST(requestBody);
  String response = http.getString();

  Serial.print("HTTP Code: ");
  Serial.println(httpCode);

  if (httpCode <= 0) {
    Serial.print("HTTP Error: ");
    Serial.println(http.errorToString(httpCode));
  }

  Serial.println("Server Response:");
  Serial.println(response);

  if (httpCode == 201 || httpCode == 200) {
    StaticJsonDocument<1024> doc;
    DeserializationError error = deserializeJson(doc, response);

    successBeep();

    if (! error) {
      const char* status = doc["status"] | "accepted";
      const char* guardNameC = doc["guard"]["name"] | "Guard Found";

      String guardName = String(guardNameC);

      Serial.println("RFID ACCEPTED");
      Serial.print("Guard: ");
      Serial.println(guardName);
      Serial.print("Status: ");
      Serial.println(status);
      Serial.println("Face verification required");

      showGuardName(guardName);
    } else {
      Serial.println("RFID Accepted");
      Serial.println("Face verification required");

      lcdMessage("RFID Accepted", "Face Required");
    }
  } else if (httpCode == 409) {
    Serial.println("Face registration or schedule issue");

    failedBeep();
    lcdMessage("Face Register", "Required");
  } else if (httpCode == 422) {
    Serial.println("Invalid RFID Scan");
    Serial.println("Check guard/card/checkpoint");

    if (isUnknownRfidResponse(response)) {
      unknownRfidBeep();
      lcdMessage("Unknown RFID", "Not Registered");
    } else {
      failedBeep();
      lcdMessage("Invalid Scan", "Check Device");
    }
  } else if (httpCode == 404) {
    Serial.println("RFID not found");

    unknownRfidBeep();
    lcdMessage("Unknown RFID", "Not Registered");
  } else if (httpCode <= 0) {
    Serial.println("No Server Reply");
    Serial.println("Check Render API/WiFi");

    failedBeep();
    lcdMessage("No Server Reply", "Check API/WiFi");
  } else {
    Serial.print("Server Error: ");
    Serial.println(httpCode);

    failedBeep();
    lcdMessage("Server Error", String(httpCode));
  }

  http.end();

  finishScanScreen();
}

void setup() {
  Serial.begin(115200);
  delay(1000);

  pinMode(BUZZER_PIN, OUTPUT);
  digitalWrite(BUZZER_PIN, LOW);

  Wire.begin(LCD_SDA_PIN, LCD_SCL_PIN);
  lcd.init();
  lcd.backlight();

  lcdMessage("Campus RFID", "Starting...");

  Serial.println("Campus RFID Patrol System");
  Serial.println("IT Building Checkpoint");

  SPI.begin();
  rfid.PCD_Init();

  Serial.println("RFID Reader Ready");

  connectWiFi();

  Serial.println("Ready to Scan RFID Card");
}

void loop() {
  if (! rfid.PICC_IsNewCardPresent()) {
    return;
  }

  if (! rfid.PICC_ReadCardSerial()) {
    return;
  }

  String uid = getCardUid();

  Serial.print("Scanned UID: ");
  Serial.println(uid);

  lcdMessage("Card Detected", uid);

  if (uid == lastUid && millis() - lastScanTime < SCAN_COOLDOWN_MS) {
    Serial.println("Duplicate Scan");
    lcdMessage("Duplicate Scan", "Please wait");

    rfid.PICC_HaltA();
    rfid.PCD_StopCrypto1();

    delay(1000);
    showReady();
    return;
  }

  lastUid = uid;
  lastScanTime = millis();

  Serial.println("Processing RFID...");
  lcdMessage("Processing", "Please wait...");

  sendScanToLaravel(uid);

  rfid.PICC_HaltA();
  rfid.PCD_StopCrypto1();

  Serial.println("---------------------");
  Serial.println("Ready for next scan");
}
