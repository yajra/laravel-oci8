#!/usr/bin/env bash

# install deps
sudo apt-get update -qq
sudo apt-get -y install -qq build-essential unzip wget libaio1

# install oci8 libs & extension
sudo mkdir -p /opt/oracle

wget https://github.com/bumpx/oracle-instantclient/raw/master/instantclient-basic-linux.x64-12.1.0.2.0.zip
wget https://github.com/bumpx/oracle-instantclient/raw/master/instantclient-sdk-linux.x64-12.1.0.2.0.zip

sudo unzip -o ./instantclient-basic-linux.x64-12.1.0.2.0.zip -d /opt/oracle
sudo unzip -o ./instantclient-sdk-linux.x64-12.1.0.2.0.zip -d /opt/oracle

sudo ln -s /opt/oracle/instantclient/sqlplus /usr/bin/sqlplus
sudo ln -s /opt/oracle/instantclient_12_1 /opt/oracle/instantclient
sudo ln -s /opt/oracle/instantclient/libclntsh.so.12.1 /opt/oracle/instantclient/libclntsh.so
sudo ln -s /opt/oracle/instantclient/libocci.so.12.1 /opt/oracle/instantclient/libocci.so

sudo sh -c "echo 'instantclient,/opt/oracle/instantclient' | pecl install oci8"

# setup ld library path
sudo sh -c "echo '/opt/oracle/instantclient' >> /etc/ld.so.conf"
sudo ldconfig

# enable oci8 extension
sudo sh -c "echo 'extension=oci8.so' >> /etc/php/8.2/cli/php.ini"
