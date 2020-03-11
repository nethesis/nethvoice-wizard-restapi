#!/usr/bin/env bats

#
# Copyright (C) 2019 Nethesis S.r.l.
# http://www.nethesis.it - nethserver@nethesis.it
#
# This script is part of NethServer.
#
# NethServer is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License,
# or any later version.
#
# NethServer is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with NethServer.  If not, see COPYING.
#

setup () {
    load nethvoice_client
}

@test "POST /freepbx/rest/phones/reboot/00-11-22-33-44-55/23/59 (success)" {
    run POST /freepbx/rest/phones/reboot/00-11-22-33-44-55/23/59<<EOF
{}
EOF
    assert_http_code "201"
}

@test "Authenticated GET /freepbx/phones/reboot" {
    run GET /freepbx/rest/phones/reboot
    assert_http_code "200"
    assert_http_body '"00-11-22-33-44-55":{"hours":"23","minutes":"59"}}'
}

@test "DELETE /freepbx/rest/phones/reboot/00-11-22-33-44-55 (success)" {
    run DELETE /freepbx/rest/phones/reboot/00-11-22-33-44-55
    assert_http_code "204"
}

