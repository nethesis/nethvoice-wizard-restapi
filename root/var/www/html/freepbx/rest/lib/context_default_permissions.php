<?php
$context_default_permissions = array(
    "parkedcalls" => ["allow" => "yes", "50"],
    "from-internal-custom" => ["allow" => "yes", "50"],
    "from-internal-additional" => ["allow" => "no", "50"],
    "ext-bosssecretary" => ["allow" => "yes", "51"],
    "app-cf-toggle" => ["allow" => "yes", "52"],
    "app-cf-busy-prompting-on" => ["allow" => "yes", "53"],
    "app-cf-busy-on" => ["allow" => "yes", "54"],
    "app-cf-busy-off-any" => ["allow" => "yes", "55"],
    "app-cf-busy-off" => ["allow" => "yes", "56"],
    "app-cf-off" => ["allow" => "yes", "57"],
    "app-cf-off-any" => ["allow" => "yes", "58"],
    "app-cf-unavailable-prompt-on" => ["allow" => "yes", "59"],
    "app-cf-unavailable-on" => ["allow" => "yes", "60"],
    "app-cf-unavailable-off" => ["allow" => "yes", "61"],
    "app-cf-on" => ["allow" => "yes", "62"],
    "app-cf-prompting-on" => ["allow" => "yes", "63"],
    "ext-cf-hints" => ["allow" => "yes", "64"],
    "app-callwaiting-cwoff" => ["allow" => "yes", "65"],
    "app-callwaiting-cwon" => ["allow" => "yes", "66"],
    "ext-meetme" => ["allow" => "yes", "67"],
    "app-dnd-off" => ["allow" => "yes", "68"],
    "app-dnd-on" => ["allow" => "yes", "69"],
    "app-dnd-toggle" => ["allow" => "yes", "60"],
    "ext-dnd-hints" => ["allow" => "yes", "71"],
    "app-fax" => ["allow" => "yes", "72"],
    "app-fmf-toggle" => ["allow" => "yes", "73"],
    "ext-findmefollow" => ["allow" => "yes", "74"],
    "fmgrps" => ["allow" => "yes", "75"],
    "app-calltrace" => ["allow" => "yes", "76"],
    "app-echo-test" => ["allow" => "yes", "77"],
    "app-speakextennum" => ["allow" => "yes", "78"],
    "app-speakingclock" => ["allow" => "yes", "79"],
    "ext-intercom-users" => ["allow" => "yes", "80"],
    "park-hints" => ["allow" => "yes", "81"],
    "app-parking" => ["allow" => "yes", "82"],
    "ext-queues" => ["allow" => "yes", "83"],
    "app-rapidcode" => ["allow" => "yes", "84"],
    "app-recordings" => ["allow" => "yes", "85"],
    "ext-group" => ["allow" => "yes", "86"],
    "grps" => ["allow" => "yes", "87"],
    "vmblast-grp" => ["allow" => "yes", "88"],
    "app-dialvm" => ["allow" => "yes", "89"],
    "app-vmmain" => ["allow" => "yes", "90"],
    "app-blacklist" => ["allow" => "yes", "91"],
    "cti-conference" => ["allow" => "yes", "92"],
    "ext-local-confirm" => ["allow" => "yes", "93"],
    "findmefollow-ringallv2" => ["allow" => "yes", "94"],
    "app-pickup" => ["allow" => "yes", "95"],
    "app-chanspy" => ["allow" => "yes", "96"],
    "ext-test" => ["allow" => "yes", "97"],
    "ext-local" => ["allow" => "yes", "98"],
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
