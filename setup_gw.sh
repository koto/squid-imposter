#!/bin/bash
# This script will setup firewall/routing settings so as to act as a gateway
# to $INTERNET interface for $LAN_IN interface clients.
# all port 80 traffic may be forwarded to local squid installation serving
# squid-imposter. See also squid.conf file
#
# Note: You also need to convince $LAN_IN clients to connect through you.
# You can use airsnarf project for setting up rogue access-point with DNS/DHCP
# or use aprspoof attack on existing network (see e.g. sslstip project)
#
# Usage:
# $ sudo ./setup.gw [squid-proxy-ip]
#
# If proxy ip will be omitted, no transparent proxy will be used.
#
# Author: Krzysztof Kotowicz <kkotowicz at gmail dot com>
# @see http://blog.kotowicz.net
# @see http://github.com/koto/squid-imposter
SQUID_SERVER="$1"
SQUID_PORT="8080"
INTERNET="eth1"
LAN_IN="eth2"

echo "Acting as gateway to $INTERNET for hosts from $LAN_IN"

iptables -F
iptables -X
iptables -t nat -X
iptables -t nat -F
iptables -t mangle -X
iptables -t mangle -F
modprobe ip_conntrack
modprobe ip_conntrack_ftp
echo 1 > /proc/sys/net/ipv4/ip_forward
iptables -P INPUT DROP
iptables -P OUTPUT ACCEPT
# unlimited loopback
iptables -A INPUT -i lo -j ACCEPT
iptables -A OUTPUT -o lo -j ACCEPT
# allow UDP,DNS
iptables -A INPUT -i $INTERNET -m state --state ESTABLISHED,RELATED -j ACCEPT
# set as router for lan
iptables --table nat --append POSTROUTING --out-interface $INTERNET -j MASQUERADE
iptables --append FORWARD --in-interface $LAN_IN -j ACCEPT
# unlimited lan access
iptables -A INPUT -i $LAN_IN -j ACCEPT
iptables -A OUTPUT -o $LAN_IN -j ACCEPT
if [ "$SQUID_SERVER" != "" ]; then
    # setup transparent proxy
    iptables -t nat -A PREROUTING -i $LAN_IN -p tcp --dport 80 -j DNAT --to $SQUID_SERVER:$SQUID_PORT
    iptables -t nat -A PREROUTING -i $INTERNET -p tcp --dport 80 -j REDIRECT --to-port $SQUID_PORT
    # drop and log
#    iptables -A INPUT -j LOG
#    iptables -A INPUT -j DROP
    echo "Port 80 traffic will be forwarded to transparent proxy on $SQUID_SERVER:$SQUID_PORT"
else
    echo "No transparent proxy. Use '$0 <ip>' to forward traffic through proxy"
fi

