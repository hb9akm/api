# Switzerland data
This directory contains all raw data files and the scripts need to normalize
data.

## TODOs:
- Repeater coordinates are not very accurate
- Only voice repeaters are listed
- Bandplan is missing
- Simplify process

## Current process
- Latest XLS file "Frequenzliste Voice-Repeater HB9 HB0" is downloaded from
  https://www.uska.ch/die-uska/uska-fachstellen/frequenzkoordination/
- Header is manually deleted and file saved as CSV (with comma, not semicolon)
- Latest XLS file "Frequenzliste ATV-Relais HB9 HB0" is downloaded from
  https://www.uska.ch/die-uska/uska-fachstellen/frequenzkoordination/
- Header is manually deleted and file saved as CSV (with comma, not semicolon)
- Latest XLS file "Frequenzliste Packet-Radio Digipeater HB9 HB0" is downloaded from
  https://www.uska.ch/die-uska/uska-fachstellen/frequenzkoordination/
- Header is manually deleted and file saved as csv (with comma, not semicolon)
- parseCHRepeaterList.php is executed on the CSV files to normalize data and add coordinates
  docker run -v$PWD:/my/working/dir/ -w/my/working/dir/ php:cli php data/CH/parseCHRepeaterList.php data/CH/201008-USKA_Frequenzliste_Voice.csv data/CH/170514-USKA_Frequenzliste_ATV.csv > data/CH/201008-USKA_Frequenzliste_Voice.json
- The resulting file is converted to JSON using an online service.
