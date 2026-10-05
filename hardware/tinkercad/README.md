# SLSU BC Patrol Tinkercad Device Model

This folder contains a Tinkercad-ready 3D reference model based on the provided technical device design.

## Modeled Measurements

- Enclosure width: 120 mm
- Enclosure height: 200 mm
- Enclosure depth: 75 mm
- Front elements: black ABS LCD bezel, LCD, acrylic LCD cover, scan sticker, four screws
- Side element: buzzer
- Top element: DC barrel jack power input

## Files

- `slsu-bc-patrol-device-model.obj` - import this into Tinkercad.
- `slsu-bc-patrol-device-model.mtl` - material/color reference for apps that read OBJ materials.
- `slsu-bc-patrol-device-model.stl` - fallback import file if OBJ upload is not accepted.
- `slsu-bc-patrol-device-model.zip` - packaged copy of the OBJ, MTL, STL, and this README.
- `generate-device-model.cjs` - generator used to rebuild the model.

## Tinkercad Import

1. Open the Tinkercad design.
2. Click **Import**.
3. Choose `slsu-bc-patrol-device-model.obj`. If that does not upload, choose `slsu-bc-patrol-device-model.stl`.
4. Keep the unit scale in millimeters.
5. The file is exported upright for Tinkercad, using X as width, Y as depth, and Z as height.
6. If colors are not preserved by Tinkercad, recolor the imported shape manually. Tinkercad often ignores OBJ/MTL material colors.

Note: The model is intended as a scaled visual replica for design presentation and layout planning. It is not yet a printable mechanical enclosure with internal mounts or screw bosses.
