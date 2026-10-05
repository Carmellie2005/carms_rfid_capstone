# SLSU BC Patrol Onshape Device Model

This folder contains Onshape-ready files based on the hand-drawn technical device design.

## Main Measurements

- Enclosure body: 120 mm width x 200 mm height x 75 mm depth
- Overall imported size is slightly larger because the side buzzer, front acrylic/LCD cover, screws, and top barrel jack protrude from the enclosure.

## Included Details

- White plastic enclosure
- Black ABS LCD bezel/front panel
- Blue LCD display with acrylic cover
- Lower scan sticker with RFID/card icon and SCAN HERE text approximation
- Four front screws
- Side buzzer
- Top DC barrel jack power input

## Recommended Onshape Import

Use this first:

- `slsu-bc-patrol-onshape-model.obj`

If OBJ does not translate, use:

- `slsu-bc-patrol-onshape-model.stl`

The previous 3MF option is kept as an experimental file, but Onshape may fail to translate it depending on the importer.

## Onshape Import Steps

1. Open or create an Onshape document.
2. Click the **+** button at the bottom-left tab area.
3. Choose **Import**.
4. Select `slsu-bc-patrol-onshape-model.obj`.
5. Keep the unit scale in millimeters.
6. Leave **Orient imported models with Y Axis Up** unchecked.

## Onshape-Native Option

For a cleaner editable Onshape build, use:

- `slsu-bc-patrol-device.featurescript`

Create a **Feature Studio**, paste the file contents, commit it, then insert the custom **SLSU BC Patrol Device** feature into the Part Studio.

## Material Groups

- white_plastic: 112 triangles
- black_abs: 312 triangles
- black_hole: 636 triangles
- blue_lcd: 12 triangles
- clear_acrylic: 12 triangles
- acrylic_highlight: 12 triangles
- sticker_white: 12 triangles
- sticker_blue: 1188 triangles
- metal_dark: 592 triangles
- metal_light: 240 triangles
- case_shadow: 24 triangles
