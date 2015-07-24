#!/bin/sh
#---------------------------------------------------------------------------
# $Id: rcpasswd.sh 339 2015-07-20 23:51:58Z mgleason $
#---------------------------------------------------------------------------
# Set this to the hostname or IP address of REDCap's MySQL database server.
redcap_dbhost="localhost"

# Set this to the database used by REDCap.
redcap_dbname="redcap"

# You must either use a mysql --defaults-file or specify the host and user.
my_redcap_cnf="/etc/my.redcap.cnf"

# You must set redcap_dbuser if you are not using a defaults file.
redcap_dbuser=""
redcap_dbpass=""	# Leave blank to be prompted (3 times in total)

# No more configuration required beyond this point.

# Here's an example defaults file:
#
# > cat /etc/my.redcap.cnf 
# [client]
# socket=/var/lib/mysql/mysql.sock
# user=redcap
# password=PasswordForRedcapUserGoesHere
#
# > ls -l /etc/my.redcap.cnf 
# -rw-r----- 1 root redcap 84 Jul  9 15:15 /etc/my.redcap.cnf
#---------------------------------------------------------------------------

cannot_access_redcap_database () {
	echo "Cannot access REDCap's MySQL database." 1>&2
	if [ -n "$redcap_dbuser" ] ; then
		echo "Check the credentials for $redcap_dbuser on the database $redcap_dbname on $redcap_dbhost." 1>&2
	elif [ ! -f "$my_redcap_cnf" ] ; then
		echo "The REDCap database user's mysql defaults file ($my_redcap_cnf) does not exist." 1>&2
	else
		/bin/ls -ld "$my_redcap_cnf" 1>&2
	fi
	exit 1
}

redcap_sql_query () {
	if [ -z "$redcap_dbuser" ] ; then
		echo "$@"  |  mysql --defaults-file="$my_redcap_cnf" -h "$redcap_dbhost" | sed -n '2,$p'
	else
		echo "$@"  |  mysql -h "$redcap_dbhost" -u "$redcap_dbuser" -p "$redcap_dbpass" | sed -n '2,$p'
	fi
}

run_redcap_sql () {
	if [ -z "$redcap_dbuser" ] ; then
		echo "\"""$@""\"" '|' mysql --defaults-file="$my_redcap_cnf" --table -h "$redcap_dbhost"
		echo "$@"  |  mysql --defaults-file="$my_redcap_cnf" --table -h "$redcap_dbhost"
	else
		echo "\"""$@""\"" '|' mysql --table -h "$redcap_dbhost" -u "$redcap_dbuser" -p "XXXXXXXX"
		echo "$@"  |  mysql --table -h "$redcap_dbhost" -u "$redcap_dbuser" -p "$redcap_dbpass"
	fi
}

if ! which "sha512sum" > /dev/null ; then
	echo "rcpasswd requires that the sha512sum program be installed." 1>&2
	exit 1
fi

if [ ! -r "$my_redcap_cnf" ] ; then
	cannot_access_redcap_database 
fi

username=""
pw_cleartext=""

Usage () {
	if [ "$#" -gt 0 ] ; then echo "$@" 1>&2 ; fi
	echo "Usage: rcpasswd -u <username> [-p password]" 1>&2
	exit 2
}

OPTS=$(getopt -o "u:p:" -n "rcpasswd" -- "$@");
if [ $? -ne 0 ] ; then Usage; fi
eval set -- "$OPTS"

while [ $# -gt 0 ]
do
	arg="$1" ; shift
	case "$arg" in
		"-u")
			if [ $# -le 0 ] ; then Usage; fi
			username="$1"; shift
			;;
		"-p")
			if [ $# -gt 0 ] ; then
				pw_cleartext="$1"; shift
			fi
			;;
		"--")
			break ;;
	esac
done

if [ -z "$username" ] ; then
	Usage "User not specified."
fi

# Prompt for the new password if not specified on the command line.
while [ -z "$pw_cleartext" ]
do
	stty -echo
	echo -n "Set new password for $username: "
	read pw_cleartext
	echo
	echo -n "Verify new password for $username: "
	read pw_cleartext2
	echo
	stty echo
	if [ "$pw_cleartext" = "$pw_cleartext2" ] ; then
		echo
		pw_cleartext2="XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"
		break
	fi
	echo
	echo "* Passwords differed."
	echo
	pw_cleartext=""
done

# Make sure we can access the database and that there are table-based users.
n_redcap_table_users=`redcap_sql_query "select COUNT(*) from $redcap_dbname.redcap_auth;"`
if [ -z "$n_redcap_table_users" ] ; then
	cannot_access_redcap_database 
elif [ "$n_redcap_table_users" -le 0 ] ; then
	echo "No Table-Based users found in REDCap's MySQL database." 1>&2
	exit 1
fi

# Query the user's password salt, which was generated randomly by REDCap.
password_salt=`redcap_sql_query "select password_salt from $redcap_dbname.redcap_auth where username = '${username}';"`
if [ -z "$password_salt" ] ; then
	echo "Could not query user record for $username." 1>&2
	cannot_access_redcap_database 
fi

# Create the encrypted (hashed) password using the cleartext version and the salt.
password=`printf "%s" "${pw_cleartext}${password_salt}" | sha512sum -b | sed 's/[\ \	].*$//;'`
pw_cleartext="XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX"

# Update the user's record in the database.
run_redcap_sql "update $redcap_dbname.redcap_auth set password = '${password}' where username = '${username}';"
exit "$?"
