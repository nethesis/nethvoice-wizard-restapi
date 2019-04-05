Name: nethvoice-wizard-restapi
Version: 14.3.1
Release: 1%{?dist}
Summary: Rest API for FreePBX
Group: Network
License: GPLv2
Source0: %{name}-%{version}.tar.gz
BuildRequires: nethserver-devtools
Buildarch: noarch
Requires: nethserver-freepbx

%description
Rest API for FreePBX

%prep
%setup


%build
perl createlinks

%install
rm -rf %{buildroot}
(cd root; find . -depth -print | cpio -dump %{buildroot})

%{genfilelist} %{buildroot} \
> %{name}-%{version}-filelist


%clean
rm -rf %{buildroot}

%files -f %{name}-%{version}-filelist
%defattr(-,root,root,-)
%dir %{_nseventsdir}/%{name}-update
%attr (0774,root,root) /var/www/html/freepbx/rest/lib/scanHelper.py
%attr (0754,root,asterisk) /var/www/html/freepbx/rest/lib/retrieveHelper.sh
%doc

%changelog
* Fri Apr 05 2019 Stefano Fancello <stefano.fancello@nethesis.it> - 14.3.1-1
- Fix getUserID() on AD nethesis/dev#5598
- Speed up csvimport nethesis/dev#5599

* Tue Mar 26 2019 Stefano Fancello <stefano.fancello@nethesis.it> - 14.3.0-1
- Add phonebook API. nethesis/dev#5557
- make app mac address recognition case insensitive
- Fix Patton FXS configuration: - delete from-internal context in autentication configuration

* Thu Feb 14 2019 Stefano Fancello <stefano.fancello@nethesis.it> - 14.2.2-1
- minor fix Vega 50 24 FXS template. nethesis/dev#5579

* Fri Feb 08 2019 Stefano Fancello <stefano.fancello@nethesis.it> - 14.2.1-1
- Add Panasonic phones to maps Nethesis/dev#5576
- Exclude Sangoma gateways when scanning for phones Nethesis/dev#5567
- Only show unknown devices as GS Wave devices Nethesis/dev#5567
 - Add operator panel queues permissions Nethesis/dev#5549
- Add operator panel macro permission Nethesis/dev#5549
- Move default CTI macro permissions and permissions 

* Fri Jan 11 2019 Stefano Fancello <stefano.fancello@nethesis.it> - 14.2.0-1
- Removed ad_sms,sms,call_waiting,oppanel permission nethesis/dev#5553
- create new webrtc extensions with h264 enabled nethesis/dev#5546
- Allows to generate configuration for GS Wave App nethesis/dev#5531
- Nethvoice 11 to 14 upgrade wizard restapi (#72) nethesis/dev#5454

* Tue Nov 20 2018 Stefano Fancello <stefano.fancello@nethesis.it> - 14.1.15-1
- Add Fanvil device and mac address nethesis/dev#5515
- Insert CTI permissions into mysql only if it is needed nethesis/dev#5499

* Tue Nov 13 2018 Stefano Fancello <stefano.fancello@nethesis.it> - 14.1.14-1
- Add username and displayname to devices object nethesis/dev#5493

* Thu Oct 04 2018 Stefano Fancello <stefano.fancello@nethesis.it> - 14.1.13-1
- Allow to specify mac address in gateway configuration download request nethesis/dev#5472

* Fri Sep 28 2018 Stefano Fancello <stefano.fancello@nethesis.it> - 14.1.12-1
- Don't use broken core function for trunk name nethesis/dev#5475
- Bulk: write outboundcid to astdb nethesis/dev#5474

* Mon Sep 24 2018 Stefano Fancello <stefano.fancello@nethesis.it> - 14.1.11-1
 - Fix indexes of scan device response

* Fri Sep 21 2018 Stefano Fancello <stefano.fancello@nethesis.it> - 14.1.10-1
- Fix missing dialoutprefix in trunks creation. nethesis/dev#5425
- Create new pjsip trunks with 0.0.0.0-udp as transport. nethesis/dev#5425
- README: removed obsolete API DELETE /mainextensions/{extension}
- Check if there if provisioning files to copy to avoid error logs

* Fri Jul 27 2018 Stefano Fancello <stefano.fancello@nethesis.it> - 14.1.9-1
- Minor changes to Xstream VoIP provider for new installations
- Add Xera voip provider
- Add gigaset mac address to type map
- Fix failed testauth return code
- Add phones from tftp requests nethesis/dev#5427

* Mon Jul 02 2018 Stefano Fancello <stefano.fancello@nethesis.it> - 14.1.8-1
- Fix trunk creation for updated version of FreePBX core module nethesis/dev#5426
- Remove user from CTI groups if is main extension is deleted
- Qmanager permission, fix permission removal nethesis/dev#5416

* Tue Jun 12 2018 Stefano Fancello <stefano.fancello@nethesis.it> - 14.1.7-1
- Fix phone scan when more than one network is searched

* Fri Jun 08 2018 Stefano Fancello <stefano.fancello@nethesis.it> - 14.1.6-1
- Remove CSV header and sample line

* Wed Jun 06 2018 Stefano Fancello <stefano.fancello@nethesis.it> - 14.1.5-1
- Fix: only add phones once when scanning overlapping networks
- Fix admin password escape
- New API and sql table for parameterized URLs nethesis/dev#5412
- Fix voicemail delete
- Add outbound cid to bulk API nethesis/dev#5402
- Use SCL to execute external php nethesis/dev#5406
- Add csv export API nethesis/dev#5411
- Add cellphone,voicemail,webrtc CTI groups and CTI profile to CSV import nethesis/dev#5411
- Patton analog: add mute-dialing to prevent incorrect ringing from the provider.

* Tue May 08 2018 Stefano Fancello <stefano.fancello@nethesis.it> - 14.1.4-1
- Allow to configure two gateways with same brand and model nethesis/dev#5390
- Sangoma 60 FXO model: fix disconnect signal for fxo ports

* Fri Apr 20 2018 Stefano Fancello <stefano.fancello@nethesis.it> - 14.1.3-1
- Add Gigaset MAC address nethesis/dev#5327

* Thu Apr 19 2018 Stefano Fancello <stefano.fancello@nethesis.it> - 14.1.2-1
- CSV import: more permissive regexp for base64

* Thu Apr 12 2018 Stefano Fancello <stefano.fancello@nethesis.it> - 14.1.1-1
- Disable callwaiting on new extensions created nethesis/dev#5377
- Add support for Sangoma 60 Gateways nethesis/dev#5372
- CSV Import API: accept different file format for Windows compatibilty nethesis/dev#5371

* Fri Apr 06 2018 Stefano Fancello <stefano.fancello@nethesis.it> - 14.1.0-1
- Add import from CSV file rest APIs nethesis/dev#5371
- Add /bulk GET and POST rest API nethesis/dev#5365
- Launch sql scripts on update event nethesis/dev#5370
- Error if username starts with a number
- Check if userman directory is locked before associating extension to user nethesis/dev#5368
- move extension creating into libExtension library
- CustomerCard: retrieve conf after a new db connection is created nethesis/dev#5362
- Change VoipVoice VoIP trunk options nethesis/dev#5359

* Fri Mar 16 2018 Stefano Fancello <stefano.fancello@nethesis.it> - 14.0.1-1
- Hide ldapservice user from wizard interface nethesis/dev#5357
- Recognize Sangoma Vega 60 Gateways nethesis/dev#5357

* Fri Feb 23 2018 Stefano Fancello <stefano.fancello@nethesis.it> - 14.0.0-1
- Don't configure AORs if gateway is only FXS. Nethesis/dev#5342
- Package separated from nethserver-nethvoice14. Nethesis/dev#5341

