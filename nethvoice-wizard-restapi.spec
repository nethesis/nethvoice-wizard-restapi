Name: nethvoice-wizard-restapi
Version: 14.1.1
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

