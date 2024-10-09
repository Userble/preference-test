# Preference Test README

## Overview
This PHP tool allows users to vote on one of two image options. It records votes in a MySQL database and provides basic vote count statistics. Created by https://www.Userble.org - Open Source Usability Testing.

### Setup

**Edit Database Credentials:** Update the following variables with your MySQL credentials in the script:

$host = 'localhost';
$dbname = 'YOUR DB NAME';
$user = 'YOUR DB USERNAME';
$pass = 'YOUR DB PASSWORD';

**Database Table Creation:** The tool automatically creates a preference_test_votes table if it doesn't exist.

**Security:** This tool uses security headers to prevent common vulnerabilities.

### Usage

**Voting:** Users can vote for either image by clicking on "Choose this image" buttons.

**Restrictions:** Limits users to one vote per session and up to 3 votes per hour from the same IP address.

**Contributions:** Contributions are welcome! If you'd like to enhance the project or fix bugs, please submit a pull request or open an issue.

### Requirements
Works on most shared / managed hosting.
PHP 7.4+
MySQL

### License
This project is free and open source, available under The ILO's Open License (https://www.theilo.org).
