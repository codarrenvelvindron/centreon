read -p 'Poller ID ? ' pollerid
read -p 'Central IP address ? ' centralipaddress
sed -i -e 's/<poller_id>.*<\/poller_id>/<poller_id>'"$pollerid"'<\/poller_id>/' /etc/centreon-broker/poller-module.xml
sed -i -e 's/<host>.*<\/host>/<host>'"$centralipaddress"'<\/host>/g' /etc/centreon-broker/poller-module.xml
/etc/init.d/centengine restart
