# Image Voting Tool README

## Overview
This PHP tool allows users to vote on one of two image options. It records votes in a MySQL database and provides basic vote count statistics.

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

### Requirements
Works on most shared / managed hosting.
PHP 7.4+
MySQL
