Name: nethvoice-wizard-restapi
Version: 14.0.0
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
