# SLSU Bontoc Patrol ESP32 PlatformIO Project

This project contains the ESP32 firmware for the SLSU Bontoc Patrol system.

## Device Roles

- `enrollment` - used on the Guard Management page. Click **Scan Card**, then tap an RFID card. The UID is sent to `/api/rfid-enrollment`.
- `checkpoint_gh` - Guard House checkpoint reader.
- `checkpoint_it` - IT Building checkpoint reader.
- `checkpoint_mpc` - MPC checkpoint reader.
- `checkpoint_fi` - FI checkpoint reader.
- `checkpoint_canteen` - Canteen checkpoint reader.
- `checkpoint_ag` - AG checkpoint reader.

## Wiring

MFRC522 RFID reader:

- SDA/SS -> GPIO 5
- RST -> GPIO 27
- SCK -> GPIO 18
- MOSI -> GPIO 23
- MISO -> GPIO 19
- 3.3V -> 3.3V
- GND -> GND

LCD I2C:

- SDA -> GPIO 21
- SCL -> GPIO 22
- VCC -> 5V or 3.3V, depending on your LCD module
- GND -> GND

Buzzer:

- Signal -> GPIO 26
- GND -> GND

## WiFi Secrets

Real WiFi credentials are stored in `include/secrets.h`. That file is ignored by Git.

If it is missing, copy:

```text
include/secrets.example.h
```

to:

```text
include/secrets.h
```

then edit the WiFi name and password.

## Upload Examples

Open this folder in VS Code with PlatformIO:

```text
hardware/slsu_bontoc_patrol_pio
```

Upload enrollment reader:

```bash
pio run -e enrollment -t upload
```

Upload Guard House checkpoint:

```bash
pio run -e checkpoint_gh -t upload
```

Upload IT checkpoint:

```bash
pio run -e checkpoint_it -t upload
```

Serial monitor:

```bash
pio device monitor -b 115200
```

## URLs

The firmware sends data to:

```text
https://slsubcpatrol.site
```

Enrollment API:

```text
/api/rfid-enrollment
```

Patrol checkpoint API:

```text
/api/rfid-scan
```
