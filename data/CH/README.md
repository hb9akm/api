# Switzerland data
This directory contains all raw data files and the scripts need to normalize
data.

## TODOs:
- Repeater coordinates are not very accurate
- Only voice repeaters are listed
- Bandplan is missing
- Simplify process

## Current process
- Latest XLS file "Frequenzliste Voice-Repeater HB9 HBØ" is downloaded from
  https://www.uska.ch/die-uska/uska-fachstellen/frequenzkoordination/
- Header is manually deleted and file saved as CSV (with comma, not semikolon)
- parseRemarks.php is executed on the file to normalize data
- addCoords.sh is executed on the file to add the coordinates
  ./addCoords.sh 201008-USKA_Frequenzliste_Voice.csv > 201008-USKA_Frequenzliste_Voice_Coords.csv
- The resulting file is converted to JSON using an online service.
