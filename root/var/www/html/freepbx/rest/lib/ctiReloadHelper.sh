#!/bin/bash
while (ps aux | grep -q [r]etrieve_conf); do
    sleep 1
done
sleep 2
# make sure flexisip configuration is present before reload
/sbin/e-smith/expand-template /etc/nethcti/nethcti.json
/usr/bin/sudo /usr/bin/systemctl reload nethcti-server
[[ -d /opt/nethvoice-report/api/ ]] && /usr/bin/sudo /usr/bin/systemctl restart nethvoice-report-api
