#!/bin/bash

# TODO:
# - Since we know on which height the relais is we should take
#   this into account and search for best matching point
# - Normalize "Remarks" into type, subtone freq, ...
# - lonLat2Locator and locator2LonLat are only rudimentarily
#   implemented: precision is questionable, 4. pair is not
#   supported, only uppercase characters are supported

# lonLat2Locator <lon> <lat>
function lonLat2Locator {
	if [[ "$1" == "" ]]; then
		echo "-"
	elif [[ "$2" == "" ]]; then
		echo "-"
	fi
	lon="$( bc <<<"$1 + 180" )"
	lat="$( bc <<<"$2 + 90" )"
	fieldIndexLon="$( bc <<<"$lon / 20 + 65" )"
	fieldIndexLat="$( bc <<<"$lat / 10 + 65" )"
	lon="$( bc <<<"$lon % 20" )"
	lat="$( bc <<<"$lat % 10" )"
	squareIndexLon="$( bc <<<"$lon / 2" )"
	squareIndexLat="$( bc <<<"$lat / 1" )"
	subSquareIndexLon="$( bc <<<"$lon % 2 / 0.083333 + 65" )"
	subSquareIndexLat="$( bc <<<"$lat % 1 / 0.0416665 + 65" )"
	printf "\x$(printf %x $fieldIndexLon)"
	printf "\x$(printf %x $fieldIndexLat)"
	echo -n $squareIndexLon$squareIndexLat
	printf "\x$(printf %x $subSquareIndexLon)"
	printf "\x$(printf %x $subSquareIndexLat)"
}
# locator2LonLat <locator>
function locator2LonLat {
	fieldIndexLon=$(printf "%d" "'${1:0:1}")
	fieldIndexLat=$(printf "%d" "'${1:1:1}")
	squareIndexLon=${1:2:1}
	squareIndexLat=${1:3:1}
	subSquareIndexLon=$(printf "%d" "'${1:4:1}")
	subSquareIndexLat=$(printf "%d" "'${1:5:1}")
	fieldIndexLon="$( bc <<<"$fieldIndexLon - 65" )"
	fieldIndexLat="$( bc <<<"$fieldIndexLat - 65" )"
	subSquareIndexLon="$( bc <<<"$subSquareIndexLon - 65" )"
	subSquareIndexLat="$( bc <<<"$subSquareIndexLat - 65" )"

	lon="$( bc -l <<<"$subSquareIndexLon / 12" )"
	lon="$( bc -l <<<"$lon + 1 / 24" )"
	lon="$( bc <<<"$lon + $squareIndexLon * 2" )"
	lon="$( bc <<<"$lon + $fieldIndexLon * 20" )"
	lon="$( bc <<<"$lon - 180" )"
	lon="$(round "$lon" 7)"
	lat="$( bc -l <<<"$subSquareIndexLat / 24" )"
	lat="$( bc -l <<<"$lat + 1 / 48" )"
	lat="$( bc <<<"$lat + $squareIndexLat * 1" )"
	lat="$( bc <<<"$lat + $fieldIndexLat * 10" )"
	lat="$( bc <<<"$lat - 90" )"
	lat="$(round "$lat" 7)"
	echo "$lon" "$lat"
}
# round <float> <decimals>
function round {
	echo $(printf %.$2f $(echo "scale=$2;(((10^$2)*$1)+0.5)/(10^$2)" | bc))
}
#lonLat2Locator $(locator2LonLat "JN36RX")
#lonLat2Locator "-9.3839104" "39.3677239"
#exit

# getMatchingResult <nominatimResults> <locator>
function getMatchingResult {
	if [[ "$1" == "" ]]; then
		return
	fi
	echo "$1" | while read -r resp; do
		lon="$(echo "$resp" | jq -r '.lon')"
		lat="$(echo "$resp" | jq -r '.lat')"
		calcLocator="$(lonLat2Locator "$lon" "$lat")"
		if [[ "$2" == "$calcLocator" ]]; then
			echo "$resp"
			return
		fi
	done
}

first=true
while read line; do
	if [[ $first == true ]]; then
		first=false
		echo "$line,lat,lon"
		continue
	fi
	QTH="$(echo "$line" | cut -d',' -f4)"
	locator="$(echo "$line" | cut -d',' -f5)"
	resps="$(curl -s "https://nominatim.openstreetmap.org/search?format=json&q=$QTH" | jq -c '.[]')"
	relais="$(getMatchingResult "$resps" "$locator")"
	if [[ "$relais" == "" ]]; then
		lonLat=($(locator2LonLat "$locator"))
		echo "$line,${lonLat[1]},${lonLat[0]}"
		continue
	fi
	lat="$(echo "$relais" | jq '.lat')"
	lon="$(echo "$relais" | jq '.lon')"
	echo "$line,$lat,$lon"
	#break
done <"$1"

