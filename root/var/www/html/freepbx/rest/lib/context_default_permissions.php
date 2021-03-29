<?php
$context_default_permissions = array(
    "parkedcalls" => ["allow" => "yes", "0"],
    "from-internal-custom" => ["allow" => "yes", "0"],
    "from-internal-additional" => ["allow" => "no", "0"],
    "app-dnd-off" => ["allow" => "yes", "1"],
    "app-dnd-on" => ["allow" => "yes", "2"],
    "app-dnd-toggle" => ["allow" => "yes", "3"],
    "ext-dnd-hints" => ["allow" => "yes", "5"],
    "ext-findmefollow" => ["allow" => "yes", "6"],
    "fmgrps" => ["allow" => "yes", "7"],
    "app-speakingclock" => ["allow" => "yes", "8"],
    "ext-queues" => ["allow" => "yes", "9"],
    "ext-group" => ["allow" => "yes", "10"],
    "grps" => ["allow" => "yes", "11"],
    "vmblast-grp" => ["allow" => "yes", "12"],
    "app-dialvm" => ["allow" => "yes", "13"],
    "app-vmmain" => ["allow" => "yes", "14"],
    "ext-local-confirm" => ["allow" => "yes", "15"],
    "findmefollow-ringallv2" => ["allow" => "yes", "16"],
    "ext-local" => ["allow" => "yes", "17"],
    "bad-number" => ["allow" => "yes", "19"],
    "ext-bosssecretary" => ["allow" => "yes", "1"],
    "app-cf-toggle" => ["allow" => "yes", "2"],
    "app-cf-busy-prompting-on" => ["allow" => "yes", "3"],
    "app-cf-busy-on" => ["allow" => "yes", "4"],
    "app-cf-busy-off-any" => ["allow" => "yes", "5"],
    "app-cf-busy-off" => ["allow" => "yes", "6"],
    "app-cf-off" => ["allow" => "yes", "7"],
    "app-cf-off-any" => ["allow" => "yes", "8"],
    "app-cf-unavailable-prompt-on" => ["allow" => "yes", "9"],
    "app-cf-unavailable-on" => ["allow" => "yes", "10"],
    "app-cf-unavailable-off" => ["allow" => "yes", "11"],
    "app-cf-on" => ["allow" => "yes", "12"],
    "app-cf-prompting-on" => ["allow" => "yes", "13"],
    "ext-cf-hints" => ["allow" => "yes", "14"],
    "app-callwaiting-cwoff" => ["allow" => "yes", "15"],
    "app-callwaiting-cwon" => ["allow" => "yes", "16"],
    "ext-meetme" => ["allow" => "yes", "17"],
    "app-dnd-off" => ["allow" => "yes", "18"],
    "app-dnd-on" => ["allow" => "yes", "19"],
    "app-dnd-toggle" => ["allow" => "yes", "20"],
    "ext-dnd-hints" => ["allow" => "yes", "21"],
    "app-fax" => ["allow" => "yes", "22"],
    "app-fmf-toggle" => ["allow" => "yes", "23"],
    "ext-findmefollow" => ["allow" => "yes", "24"],
    "fmgrps" => ["allow" => "yes", "25"],
    "app-calltrace" => ["allow" => "yes", "26"],
    "app-echo-test" => ["allow" => "yes", "27"],
    "app-speakextennum" => ["allow" => "yes", "28"],
    "app-speakingclock" => ["allow" => "yes", "29"],
    "ext-intercom-users" => ["allow" => "yes", "30"],
    "park-hints" => ["allow" => "yes", "31"],
    "app-parking" => ["allow" => "yes", "32"],
    "ext-queues" => ["allow" => "yes", "33"],
    "app-queue-toggle" => ["allow" => "yes", "34"],
    "app-queue-caller-count" => ["allow" => "yes", "35"],
    "app-rapidcode" => ["allow" => "yes", "36"],
    "app-recordings" => ["allow" => "yes", "37"],
    "ext-group" => ["allow" => "yes", "38"],
    "grps" => ["allow" => "yes", "39"],
    "vmblast-grp" => ["allow" => "yes", "40"],
    "app-dialvm" => ["allow" => "yes", "41"],
    "app-vmmain" => ["allow" => "yes", "42"],
    "app-blacklist" => ["allow" => "yes", "43"],
    "cti-conference" => ["allow" => "yes", "44"],
    "ext-local-confirm" => ["allow" => "yes", "45"],
    "findmefollow-ringallv2" => ["allow" => "yes", "46"],
    "app-pickup" => ["allow" => "yes", "47"],
    "app-chanspy" => ["allow" => "yes", "48"],
    "ext-test" => ["allow" => "yes", "49"],
    "ext-local" => ["allow" => "yes", "50"],
);

/*
dnd
call_forward
recording
conference
parkings
chat
privacy
screen_sharing
phone_buttons
ad_phonebook
ad_cdr
spy
intrude
ad_recording
pickup
transfer
ad_parking
hangup
trunks
ad_phone
grp_all
ad_queue_agent
lost_queue_call
advanced_off_hour
ad_off_hour
qmanager_666
in_queue_666
*/
$context_permission_map = array(
    "dnd" => ["app-dnd-off","app-dnd-on","app-dnd-toggle","ext-dnd-hints","app-dnd-off","app-dnd-on","app-dnd-toggle","ext-dnd-hints"],
    "call_forward" => ["app-cf-toggle","app-cf-busy-prompting-on","app-cf-busy-on","app-cf-busy-off-any","app-cf-busy-off","app-cf-off","app-cf-off-any","app-cf-unavailable-prompt-on","app-cf-unavailable-on","app-cf-unavailable-off","app-cf-on","app-cf-prompting-on","ext-cf-hints"],
    "recording" => ["app-recordings"],
    "conference" => ["cti-conference"],
    "parkings" => ["app-parking"],
    "spy" => ["app-chanspy"],
    "pickup" => ["app-pickup"],
);
