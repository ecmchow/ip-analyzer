#!/bin/sh

# This is an example shell script for sending email using IP Analyzer

# (plaintext TCP)
echo '{"ip":"218.102.23.228"}' | timeout 3 nc -w 3 localhost 3000

# (if you enable SSL on TCP)
echo '{"ip":"218.102.23.228"}' | timeout 3 openssl s_client -connect localhost:3000 -ign_eof

exit 0