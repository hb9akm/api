# Goal
Provide normalized data in form of a read-only REST API for HAMs worldwide.

# Priorities
- Get something up and running that solves a problem
- Make it nice
- Extend it over time

# Current endpoints
- Repeater
- Bandplan
- Lexicon

# State of affairs
- The repeater endpoint returns all voice repeaters of Switzerland.
- The bandplan endpoint returns the complete band plan for Switzerland.

# Contribute
There are different ways to contribute:
- Report a mistake or question
- Add repeater source data of other countries
- Add bandplan source data of other countries
- Add other endpoints

For all ways to contribute, please create an issue on GitHub first.

# How to use it
There are several methods to use this API:
- Use the provided installation at https://api.hb9akm.ch/
- Clone this repository to any PHP capable webserver
- Use the provided Docker-Image

API documentation can be found in doc folder.

# Technical infos
- This is built using the slim micro framework.
- Data returned by the API is currently stored in JSON files.
  Storing this in a relational database is a planned feature.
- /doc/ folder contains the API documentation using Swagger.
- /data/ folder contains raw data and converters in Bash.

# License
The code is provided as is, without any warranty under GPLv2. The
data returned by the API is owned by the respective authorities.
