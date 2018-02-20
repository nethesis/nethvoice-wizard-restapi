#!/bin/bash
while (ps aux | grep -q [r]etrieve_conf); do
    sleep 1
done
sleep 2
/usr/bin/sudo /usr/bin/systemctl reload nethcti-server
