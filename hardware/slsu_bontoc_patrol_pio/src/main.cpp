#include <Arduino.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <SPI.h>
#include <MFRC522.h>
#include <ArduinoJson.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>

#include "secrets.h"

#ifndef DEVICE_MODE
#define DEVICE_MODE 1
#endif

#ifndef DEVICE_UID
#define DEVICE_UID "ESP32-REG-01"
#endif

#ifndef DEVICE_LABEL
#define DEVICE_LABEL "Enrollment Reader"
#endif

#ifndef APP_BASE_URL
#define APP_BASE_URL "https://slsubcpatrol.site"
#endif

#ifndef API_PATH
#define API_PATH "/api/rfid-enrollment"
#endif

#ifndef RFID_SS_PIN
#define RFID_SS_PIN 5
#endif

#ifndef RFID_RST_PIN
#define RFID_RST_PIN 27
#endif

#ifndef LCD_SDA_PIN
#define LCD_SDA_PIN 21
#endif

#ifndef LCD_SCL_PIN
#define LCD_SCL_PIN 22
#endif

#ifndef LCD_COLUMNS
#define LCD_COLUMNS 16
#endif

#ifndef LCD_ROWS
#define LCD_ROWS 2
#endif

#ifndef BUZZER_PIN
#define BUZZER_PIN 26
#endif

#ifndef BUZZER_ACTIVE_HIGH
#define BUZZER_ACTIVE_HIGH 1
#endif

#ifndef SCAN_COOLDOWN_MS
#define SCAN_COOLDOWN_MS 3000
#endif

#ifndef WIFI_CONNECT_ATTEMPTS
#define WIFI_CONNECT_ATTEMPTS 40
#endif

#ifndef HTTP_TIMEOUT_MS
#define HTTP_TIMEOUT_MS 65000
#endif

constexpr int MODE_ENROLLMENT = 1;
constexpr int MODE_CHECKPOINT = 2;
constexpr int buzzerOnLevel = BUZZER_ACTIVE_HIGH ? HIGH : LOW;
constexpr int buzzerOffLevel = BUZZER_ACTIVE_HIGH ? LOW : HIGH;

LiquidCrystal_I2C lcd(0x27, LCD_COLUMNS, LCD_ROWS);
MFRC522 rfid(RFID_SS_PIN, RFID_RST_PIN);

String lastUid = "";
unsigned long lastScanTime = 0;

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

void buzzerOff() {
  digitalWrite(BUZZER_PIN, buzzerOffLevel);
}

void beep(int times, int onTimeMs, int offTimeMs) {
  for (int i = 0; i < times; i++) {
    digitalWrite(BUZZER_PIN, buzzerOnLevel);
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

void failedBeep() {
  beep(3, 90, 100);
}

void unknownRfidBeep() {
  beep(4, 70, 90);
}

String apiUrl() {
  String base = APP_BASE_URL;
  String path = API_PATH;

  if (base.endsWith("/") && path.startsWith("/")) {
    base.remove(base.length() - 1);
  }

  if (! base.endsWith("/") && ! path.startsWith("/")) {
    base += "/";
  }

  return base + path;
}

void showReady() {
  if (DEVICE_MODE == MODE_ENROLLMENT) {
    Serial.println("Ready. Click Scan Card in the guard form, then tap RFID card.");
    lcdMessage("Enroll Ready", "Tap RFID Card");
  } else {
    Serial.println("Ready. Tap guard RFID card.");
    lcdMessage("RFID Ready", "Scan Card");
  }

  readyBeep();
}

void finishScanScreen(int waitMs = 2400) {
  delay(waitMs);
  showReady();
}

void printBootBanner() {
  Serial.println();
  Serial.println("=================================");
  Serial.println("SLSU Bontoc Patrol ESP32");
  Serial.println("=================================");
  Serial.print("Mode: ");
  Serial.println(DEVICE_MODE == MODE_ENROLLMENT ? "RFID Enrollment" : "Checkpoint Patrol");
  Serial.print("Device UID: ");
  Serial.println(DEVICE_UID);
  Serial.print("Device Label: ");
  Serial.println(DEVICE_LABEL);
  Serial.print("API URL: ");
  Serial.println(apiUrl());
  Serial.println("=================================");
}

void connectWiFi() {
  Serial.println();
  Serial.println("Connecting WiFi...");
  Serial.print("SSID: ");
  Serial.println(WIFI_SSID);

  lcdMessage("Connecting WiFi", WIFI_SSID);

  WiFi.mode(WIFI_STA);
  WiFi.setSleep(false);

  if (strlen(WIFI_PASSWORD) == 0) {
    WiFi.begin(WIFI_SSID);
  } else {
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  }

  int attempts = 0;

  while (WiFi.status() != WL_CONNECTED && attempts < WIFI_CONNECT_ATTEMPTS) {
    delay(500);
    Serial.print(".");
    attempts++;
  }

  Serial.println();

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("WiFi Connected");
    Serial.print("ESP32 IP: ");
    Serial.println(WiFi.localIP());

    lcdMessage("WiFi Connected", WiFi.localIP().toString());
    delay(1500);
    showReady();
  } else {
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

  return guardMissing || mentionsUnknown || (mentionsCardOrGuard && ! mentionsReaderOrCheckpoint);
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

  delay(1800);
  lcdMessage("Patrol Logged", "Thank You");
}

void handleEnrollmentResponse(int httpCode, String response) {
  if (httpCode == 200 || httpCode == 201) {
    successBeep();
    lcdMessage("Card Captured", "Form will fill");
    Serial.println("Success. The UID should appear in the guard form if Scan Card is waiting.");
  } else if (httpCode == 422) {
    failedBeep();
    lcdMessage("Invalid UID", "Check card");
    Serial.println("Invalid enrollment payload.");
  } else if (httpCode > 0) {
    failedBeep();
    lcdMessage("Server Error", String(httpCode));
    Serial.println("Server returned an error.");
  } else {
    failedBeep();
    lcdMessage("No Server Reply", "Check Internet");
    Serial.println("No server reply. Check WiFi or HTTPS.");
  }

  Serial.println("Server Response:");
  Serial.println(response);
}

void handleCheckpointResponse(int httpCode, String response) {
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
      Serial.println("Patrol logged.");

      showGuardName(guardName);
    } else {
      Serial.println("RFID accepted. Patrol logged.");
      lcdMessage("Patrol Logged", "Thank You");
    }
  } else if (httpCode == 409) {
    failedBeep();
    lcdMessage("Scan Denied", "Contact Admin");
    Serial.println("Scan denied by server.");
  } else if (httpCode == 422) {
    if (isUnknownRfidResponse(response)) {
      unknownRfidBeep();
      lcdMessage("Unknown RFID", "Not Registered");
      Serial.println("Unknown or unregistered RFID.");
    } else {
      failedBeep();
      lcdMessage("Invalid Scan", "Check Device");
      Serial.println("Invalid scan. Check card and checkpoint device UID.");
    }
  } else if (httpCode == 404) {
    unknownRfidBeep();
    lcdMessage("Unknown RFID", "Not Registered");
    Serial.println("RFID not found.");
  } else if (httpCode <= 0) {
    failedBeep();
    lcdMessage("No Server Reply", "Check API/WiFi");
    Serial.println("No server reply. Check API URL or WiFi.");
  } else {
    failedBeep();
    lcdMessage("Server Error", String(httpCode));
    Serial.print("Server Error: ");
    Serial.println(httpCode);
  }

  Serial.println("Server Response:");
  Serial.println(response);
}

void sendUidToServer(String rfidUid) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi disconnected. Reconnecting...");
    lcdMessage("WiFi Lost", "Reconnecting");

    connectWiFi();

    if (WiFi.status() != WL_CONNECTED) {
      failedBeep();
      lcdMessage("Send Failed", "No WiFi");
      finishScanScreen();
      return;
    }
  }

  String url = apiUrl();

  Serial.println();
  Serial.println("---------------------");
  Serial.println(DEVICE_MODE == MODE_ENROLLMENT ? "Sending RFID Enrollment..." : "Sending RFID Scan...");
  Serial.print("RFID UID: ");
  Serial.println(rfidUid);
  Serial.print("Device UID: ");
  Serial.println(DEVICE_UID);
  Serial.print("API URL: ");
  Serial.println(url);

  lcdMessage(DEVICE_MODE == MODE_ENROLLMENT ? "Sending UID" : "Sending Scan", rfidUid);

  WiFiClientSecure client;
  client.setInsecure();

  HTTPClient http;

  if (! http.begin(client, url)) {
    Serial.println("Invalid API URL.");
    failedBeep();
    lcdMessage("Invalid URL", "Check API");
    finishScanScreen();
    return;
  }

  http.setTimeout(HTTP_TIMEOUT_MS);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.addHeader("User-Agent", "SLSU-Bontoc-Patrol-ESP32/1.0");

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

  http.end();

  if (DEVICE_MODE == MODE_ENROLLMENT) {
    handleEnrollmentResponse(httpCode, response);
  } else {
    handleCheckpointResponse(httpCode, response);
  }

  finishScanScreen();
}

void setup() {
  Serial.begin(115200);
  delay(1800);

  pinMode(BUZZER_PIN, OUTPUT);
  buzzerOff();

  Wire.begin(LCD_SDA_PIN, LCD_SCL_PIN);
  lcd.init();
  lcd.backlight();
  lcdMessage("SLSU Patrol", "Starting...");

  printBootBanner();

  SPI.begin();
  rfid.PCD_Init();

  byte version = rfid.PCD_ReadRegister(MFRC522::VersionReg);
  Serial.print("MFRC522 Version: 0x");
  Serial.println(version, HEX);

  if (version == 0x00 || version == 0xFF) {
    Serial.println("Warning: RFID reader not detected. Check SDA/SCK/MOSI/MISO/RST/3.3V/GND wiring.");
    lcdMessage("RFID Warning", "Check wiring");
    delay(2000);
  }

  connectWiFi();
}

void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    connectWiFi();
    delay(1000);
    return;
  }

  if (! rfid.PICC_IsNewCardPresent()) {
    delay(50);
    return;
  }

  if (! rfid.PICC_ReadCardSerial()) {
    delay(50);
    return;
  }

  String uid = getCardUid();
  unsigned long nowMs = millis();

  Serial.println();
  Serial.print("Card Detected: ");
  Serial.println(uid);

  lcdMessage("Card Detected", uid);

  if (uid == lastUid && nowMs - lastScanTime < SCAN_COOLDOWN_MS) {
    Serial.println("Duplicate scan ignored. Please wait.");
    lcdMessage("Duplicate Scan", "Please wait");

    rfid.PICC_HaltA();
    rfid.PCD_StopCrypto1();

    delay(1000);
    showReady();
    return;
  }

  lastUid = uid;
  lastScanTime = nowMs;

  sendUidToServer(uid);

  rfid.PICC_HaltA();
  rfid.PCD_StopCrypto1();

  Serial.println("---------------------");
  Serial.println("Ready for next card.");
}
