#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <SPI.h>
#include <MFRC522.h>
#include <ArduinoJson.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>

// WiFi settings.
const char* WIFI_SSID = "255.255.255.252";
const char* WIFI_PASSWORD = "87654321";

// Guard registration / enrollment endpoint.
// Use this reader with Guard Management > Scan Card.
const char* API_URL = "https://slsubcpatrol.site/api/rfid-enrollment";

// Enrollment reader device ID.
#define DEVICE_UID "ESP32-REG-01"

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

const unsigned long SCAN_COOLDOWN_MS = 2500;
const int WIFI_CONNECT_ATTEMPTS = 40;
const int HTTP_TIMEOUT_MS = 15000;
const int BUZZER_BOOST_GAP_MS = 35;

LiquidCrystal_I2C lcd(0x27, LCD_COLUMNS, LCD_ROWS);
MFRC522 rfid(RFID_SS_PIN, RFID_RST_PIN);

String lastUid = "";
unsigned long lastScanTime = 0;

void playTone(int frequency, int durationMs) {
  if (frequency <= 0 || durationMs <= 0) {
    return;
  }

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

void playStrongTone(int frequency, int durationMs) {
  playTone(frequency, durationMs);
  delay(BUZZER_BOOST_GAP_MS);
  playTone(frequency, durationMs / 2);
}

void successBeep() {
  playStrongTone(2600, 180);
  delay(60);
  playStrongTone(3200, 130);
  delay(80);
}

void failedBeep() {
  playStrongTone(1200, 170);
  delay(80);
  playStrongTone(900, 170);
  delay(80);
  playStrongTone(1200, 170);
  delay(80);
}

void readyBeep() {
  playStrongTone(2000, 80);
  delay(60);
  playStrongTone(2800, 80);
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
  Serial.println("Ready. Click Scan Card in Guard Management, then tap RFID card.");
  lcdMessage("Enroll Ready", "Tap RFID Card");
  readyBeep();
}

void finishScanScreen(int waitMs = 2200) {
  delay(waitMs);
  showReady();
}

void connectWiFi() {
  Serial.println();
  Serial.println("Connecting WiFi...");
  Serial.print("SSID: ");
  Serial.println(WIFI_SSID);
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

  Serial.println();

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("WiFi Connected");
    Serial.print("IP Address: ");
    Serial.println(WiFi.localIP());
    Serial.print("Device: ");
    Serial.println(DEVICE_UID);
    Serial.print("API URL: ");
    Serial.println(API_URL);

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

void sendEnrollmentToLaravel(String rfidUid) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi disconnected. Reconnecting...");
    lcdMessage("WiFi Lost", "Reconnecting");

    connectWiFi();

    if (WiFi.status() != WL_CONNECTED) {
      Serial.println("Cannot send UID. WiFi still disconnected.");
      failedBeep();
      lcdMessage("Send Failed", "No WiFi");
      finishScanScreen();
      return;
    }
  }

  Serial.println();
  Serial.println("---------------------");
  Serial.println("Sending RFID Enrollment...");
  Serial.print("RFID UID: ");
  Serial.println(rfidUid);
  Serial.print("Device UID: ");
  Serial.println(DEVICE_UID);
  Serial.print("API URL: ");
  Serial.println(API_URL);

  lcdMessage("Sending UID", rfidUid);

  WiFiClientSecure client;
  client.setInsecure();

  HTTPClient http;

  if (!http.begin(client, API_URL)) {
    Serial.println("Invalid API URL");
    failedBeep();
    lcdMessage("Invalid URL", "Check API");
    finishScanScreen();
    return;
  }

  http.setTimeout(HTTP_TIMEOUT_MS);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.addHeader("User-Agent", "ESP32-RFID-Enrollment/1.0");

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

  http.end();

  if (httpCode == 200 || httpCode == 201) {
    successBeep();
    lcdMessage("Card Captured", rfidUid);
    Serial.println("Success. The UID should appear in the guard form if Scan Card is waiting.");
  } else if (httpCode == 422) {
    failedBeep();
    lcdMessage("Invalid UID", "Check card");
    Serial.println("Invalid enrollment payload.");
  } else if (httpCode > 0) {
    failedBeep();
    lcdMessage("Server Error", String(httpCode));
    Serial.println("Server returned an error. Check Dokploy/Laravel logs.");
  } else {
    failedBeep();
    lcdMessage("No Server Reply", "Check Internet");
    Serial.println("No server reply. Check WiFi or HTTPS connection.");
  }

  finishScanScreen();
}

void setup() {
  Serial.begin(115200);
  delay(1800);

  pinMode(BUZZER_PIN, OUTPUT);
  digitalWrite(BUZZER_PIN, LOW);

  Wire.begin(LCD_SDA_PIN, LCD_SCL_PIN);
  lcd.init();
  lcd.backlight();

  lcdMessage("RFID Enroller", "Starting...");

  Serial.println();
  Serial.println("==============================");
  Serial.println("RFID REGISTRATION READER BOOT");
  Serial.println("==============================");
  Serial.println("Purpose: Guard card enrollment");
  Serial.print("Device UID: ");
  Serial.println(DEVICE_UID);
  Serial.print("API URL: ");
  Serial.println(API_URL);

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

  if (!rfid.PICC_IsNewCardPresent()) {
    delay(50);
    return;
  }

  if (!rfid.PICC_ReadCardSerial()) {
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
    Serial.println("Duplicate card ignored. Please wait.");
    lcdMessage("Duplicate Scan", "Please wait");

    rfid.PICC_HaltA();
    rfid.PCD_StopCrypto1();

    delay(1000);
    showReady();
    return;
  }

  lastUid = uid;
  lastScanTime = nowMs;

  sendEnrollmentToLaravel(uid);

  rfid.PICC_HaltA();
  rfid.PCD_StopCrypto1();

  Serial.println("---------------------");
  Serial.println("Ready for next card.");
}
