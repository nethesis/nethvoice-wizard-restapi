<?php
$context_default_permissions = array(
    "parkedcalls" => ["allow" => "yes"],
    "from-internal-custom" => ["allow" => "yes"],
    "from-internal-additional" => ["allow" => "no"],
    "ext-bosssecretary" => ["allow" => "yes"],
    "app-cf-toggle" => ["allow" => "yes"],
    "app-cf-busy-prompting-on" => ["allow" => "yes"],
    "app-cf-busy-on" => ["allow" => "yes"],
    "app-cf-busy-off-any" => ["allow" => "yes"],
    "app-cf-busy-off" => ["allow" => "yes"],
    "app-cf-off" => ["allow" => "yes"],
    "app-cf-off-any" => ["allow" => "yes"],
    "app-cf-unavailable-prompt-on" => ["allow" => "yes"],
    "app-cf-unavailable-on" => ["allow" => "yes"],
    "app-cf-unavailable-off" => ["allow" => "yes"],
    "app-cf-on" => ["allow" => "yes"],
    "app-cf-prompting-on" => ["allow" => "yes"],
    "ext-cf-hints" => ["allow" => "yes"],
    "app-callwaiting-cwoff" => ["allow" => "yes"],
    "app-callwaiting-cwon" => ["allow" => "yes"],
    "ext-meetme" => ["allow" => "yes"],
    "app-daynight-toggle" => ["allow" => "yes"],
    "app-dnd-off" => ["allow" => "yes"],
    "app-dnd-on" => ["allow" => "yes"],
    "app-dnd-toggle" => ["allow" => "yes"],
    "ext-dnd-hints" => ["allow" => "yes"],
    "app-fax" => ["allow" => "yes"],
    "app-fmf-toggle" => ["allow" => "yes"],
    "ext-findmefollow" => ["allow" => "yes"],
    "fmgrps" => ["allow" => "yes"],
    "app-calltrace" => ["allow" => "yes"],
    "app-echo-test" => ["allow" => "yes"],
    "app-speakextennum" => ["allow" => "yes"],
    "app-speakingclock" => ["allow" => "yes"],
    "ext-intercom-users" => ["allow" => "yes"],
    "park-hints" => ["allow" => "yes"],
    "app-parking" => ["allow" => "yes"],
    "ext-queues" => ["allow" => "yes"],
    "app-queue-toggle" => ["allow" => "yes"],
    "app-queue-caller-count" => ["allow" => "yes"],
    "app-rapidcode" => ["allow" => "yes"],
    "app-recordings" => ["allow" => "yes"],
    "ext-group" => ["allow" => "yes"],
    "grps" => ["allow" => "yes"],
    "vmblast-grp" => ["allow" => "yes"],
    "timeconditions-toggles" => ["allow" => "yes"],
    "app-dialvm" => ["allow" => "yes"],
    "app-vmmain" => ["allow" => "yes"],
    "app-blacklist" => ["allow" => "yes"],
    "cti-conference" => ["allow" => "yes"],
    "ext-local-confirm" => ["allow" => "yes"],
    "findmefollow-ringallv2" => ["allow" => "yes"],
    "app-pickup" => ["allow" => "yes"],
    "app-chanspy" => ["allow" => "yes"],
    "ext-test" => ["allow" => "yes"],
    "ext-local" => ["allow" => "yes"],
    "outbound-allroutes" => ["allow" => "yes"],
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
