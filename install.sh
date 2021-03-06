#!/bin/bash

########################################################################
# Install script for Centreon 3 master on CentOS 6 (RH 6)
########################################################################

# Install base packages
yum install -y gcc rrdtool rrdtool-devel curl wget ntpdate

# To prevent restored VM having a bad date
ntpdate pool.ntp.org

# Install LA*P stack
yum install -y centos-release-SCL
yum install -y php54 php54-php-cli php54-php-mysql php54-php-xml php54-php-pdo php54-php-mbstring php54-php-devel php54-php php54-php-process php54-php-pear php54-php-gd
# FIXME for compat' with shebang of centreonConsole
ln -sf /opt/rh/php54/root/usr/bin/php /usr/bin/php

# Replace timezone in /opt/rh/php54/root/etc/php.ini
sed -i 's/^\(;date.timezone.*\)/\1\ndate.timezone = Europe\/Paris/' /opt/rh/php54/root/etc/php.ini

# Add Centreon repos (CES + internal dev)

rpm -Uvh http://yum.centreon.com/standard/3.0/stable/noarch/RPMS/ces-release-3.0-1.noarch.rpm

cat << EOF > /etc/yum.repos.d/ces-standard.repo
[ces-standard-unstable]
name=Centreon Enterprise Server development RPM repository for ces $releasever
baseurl=http://yum.centreon.com/standard/3.0/unstable/x86_64/
enabled=1
gpgcheck=1
gpgkey=file:///etc/pki/rpm-gpg/RPM-GPG-KEY-CES
EOF

# Install mariadb server and client

yum install -y MariaDB MariaDB-client

service mysql start
mysql -u root -e "grant all privileges on centreon.* to 'centreon'@'localhost' identified by 'centreon';"
mysql -u root -e "create database centreon;"

# Configure Apache
cat << EOF > /etc/httpd/conf.d/centreon.conf
<VirtualHost *:80>
  DocumentRoot /srv/centreon/www

  <Directory "/srv/centreon/www">
          Options +Indexes +FollowSymLinks
          AllowOverride All
          Order allow,deny
          Allow from all
  
        php_value output_buffering 4096
  </Directory>
</VirtualHost>
EOF
service httpd start

# Broker
# Note "*" is important to install modules
yum install -y centreon-broker*
service cbd start

# Engine
yum install -y centreon-engine
chown centreon-engine.centreon-engine /etc/centreon-engine
chmod 775 /etc/centreon-engine
usermod -G centreon-engine,centreon-broker centreon-engine
# FIXME, default conf/layout not good
rm -rf /etc/centreon-engine/objects/*
service centengine start

# Configure sudo
cat << EOF > /etc/sudoers.d/centreon
## BEGIN: CENTREON SUDO
#Add by CENTREON installation script
User_Alias      CENTREON=apache,nagios,centreon,centreon-engine,centreon-broker
Defaults:CENTREON !requiretty
## Centreontrapd Restart
CENTREON   ALL = NOPASSWD: /etc/init.d/centreontrapd restart
## CentStorage
CENTREON   ALL = NOPASSWD: /etc/init.d/centstorage *
# Centengine Restart
CENTREON   ALL = NOPASSWD: /etc/init.d/centengine restart
# Centengine stop
CENTREON   ALL = NOPASSWD: /etc/init.d/centengine start
# Centengine stop
CENTREON   ALL = NOPASSWD: /etc/init.d/centengine stop
# Centengine reload
CENTREON   ALL = NOPASSWD: /etc/init.d/centengine reload
# Centengine test config
CENTREON   ALL = NOPASSWD: /usr/sbin/centengine -v *
# Centengine test for optim config
CENTREON   ALL = NOPASSWD: /usr/sbin/centengine -s *
# Broker Central restart
CENTREON   ALL = NOPASSWD: /etc/init.d/cbd restart
# Broker Central reload
CENTREON   ALL = NOPASSWD: /etc/init.d/cbd reload
# Broker Central start
CENTREON   ALL = NOPASSWD: /etc/init.d/cbd start
# Broker Central stop
CENTREON   ALL = NOPASSWD: /etc/init.d/cbd stop
## END: CENTREON SUDO
EOF

chmod 440 /etc/sudoers.d/centreon

# Install SNMP
yum install -y perl-Net-SNMP.noarch net-snmp-perl.x86_64 net-snmp.x86_64 net-snmp-utils.x86_64
cat << EOF > /etc/snmp/snmpd.conf
####
# First, map the community name "public" into a "security name"

#       sec.name  source          community
com2sec notConfigUser  default       public

####
# Second, map the security name into a group name:

#       groupName      securityModel securityName
group   notConfigGroup v1           notConfigUser
group   notConfigGroup v2c           notConfigUser

####
# Third, create a view for us to let the group have rights to:

# Make at least  snmpwalk -v 1 localhost -c public system fast again.
#       name           incl/excl     subtree         mask(optional)
view centreon included .1.3.6.1
view    systemview    included   .1.3.6.1.2.1.1
view    systemview    included   .1.3.6.1.2.1.25.1.1

####
# Finally, grant the group read-only access to the systemview view.

#       group          context sec.model sec.level prefix read   write  notif
access notConfigGroup "" any noauth exact centreon none none
access  notConfigGroup ""      any       noauth    exact  systemview none none

includeAllDisks 10%
EOF

service snmpd start

# Install centreon-plugins
git clone https://github.com/centreon/centreon-plugins.git /usr/lib/nagios/plugins/centreon-plugins/
chmod 755 /usr/lib/nagios/plugins/centreon-plugins/centreon_plugins.pl

chown root /usr/lib/nagios/plugins/check_icmp
chmod u+s /usr/lib/nagios/plugins/check_icmp

# Install php module for rrdtools
yum install -y php54-php-pecl-rrd

# On to the PHP soft now, first let's install composer + update Centreon dependencies
curl -sS https://getcomposer.org/installer | scl enable php54 "php -- --install-dir=/tmp"
mv /tmp/composer.phar /usr/local/bin/composer
cd /srv/centreon
scl enable php54 "/usr/local/bin/composer update"

# Edit centreon.ini
sed -i 's/^\(username.=.*\)/username=centreon/' /srv/centreon/config/centreon.ini
sed -i 's/^\(password.=.*\)/password=centreon/' /srv/centreon/config/centreon.ini

external/bin/centreonConsole core:internal:install
external/bin/centreonConsole core:module:manage:install --module=centreon-broker
external/bin/centreonConsole core:module:manage:install --module=centreon-engine
external/bin/centreonConsole core:module:manage:install --module=centreon-performance 
external/bin/centreonConsole core:module:manage:install --module=centreon-bam
\cp -r modules/CentreonAdministrationModule/static/centreon-administration/ www/static/
\cp -r modules/CentreonPerformanceModule/static/centreon-performance/ www/static/
\cp -r modules/CentreonConfigurationModule/static/centreon-configuration/ www/static/

chown apache.apache /srv/centreon/www/uploads/images
chown apache.apache /srv/centreon/www/uploads/imagesthumb/

usermod -a -G centreon-engine apache
usermod -a -G centreon-broker apache

# Check and create group/user centreon
getent group centreon &>/dev/null || groupadd -r centreon
getent passwd centreon &>/dev/null || useradd -g centreon -m -d /var/lib/centreon -r centreon
usermod -a -G centreon apache

# Needed to apply new groups to the process
service httpd restart

# Create default generation directory
mkdir -p /tmp/broker/generate /tmp/broker/apply /tmp/engine/generate /tmp/engine/apply
chown centreon: /tmp/broker/generate /tmp/broker/apply /tmp/engine/generate /tmp/engine/apply
chmod g+ws /tmp/broker/generate /tmp/broker/apply /tmp/engine/generate /tmp/engine/apply
setfacl -R -m d:u:centreon:rwX,d:g:centreon:rwX,d:o:r-X /tmp/broker/generate /tmp/broker/apply /tmp/engine/generate /tmp/engine/apply

# Create RRD paths
mkdir /var/lib/centreon
mkdir /var/lib/centreon/metrics
mkdir /var/lib/centreon/status
mkdir /var/lib/centreon/centplugins
chown -R centreon-broker /var/lib/centreon/metrics
chown -R centreon-broker /var/lib/centreon/status
chown -R centreon-engine /var/lib/centreon/centplugins

# Start services
# Nothing to do, they should already be running due to previous steps

# Activate services on boot
chkconfig --level 2345 mysql on
chkconfig --level 2345 httpd on
chkconfig --level 2345 cbd on
chkconfig --level 2345 centengine on
chkconfig --level 2345 snmpd on


# Update centreon-broker default configuration
sed -i -e 's/<poller_id>.*<\/poller_id>/<poller_id>1<\/poller_id>/' /etc/centreon-broker/poller-module.xml
sed -i -e 's/<poller_name>.*<\/poller_name>/<poller_name>Central<\/poller_name>/' /etc/centreon-broker/poller-module.xml
sed -i -e 's/<broker_id>.*<\/broker_id>/<broker_id>3<\/broker_id>/' /etc/centreon-broker/poller-module.xml
sed -i -e 's/<broker_name>.*<\/broker_name>/<broker_name>poller-module-3<\/broker_name>/' /etc/centreon-broker/poller-module.xml

/etc/init.d/cbd restart
/etc/init.d/centengine restart

# FIXME We should add somewhere a oif checks like SE Linux disbaled + PHP version and so on

# End of script
