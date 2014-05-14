<?php

/**
 * @name Team-Eureka Whitelist Service
 * @author ddggttff3 (chrisrblake93@gmail.com)
 * @license GPLv3 (When we Release)
 * @version 2.6 (Updated 5/6/2014)
 * @copyright Team-Eureka 2014
 */

# DEBUG
if (isset($_GET['devmode'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

# URL of Googles Whitelist
$GooglesURL = "https://clients3.google.com/cast/chromecast/device/baseconfig?b=16664";

# Connect to database, else die/fail
$DBcon = mysqli_connect("localhost", "username", "password", "table");
$DBprefix = "pwl";

#############################################
# There is no need to edit below this line! #
#############################################

# Used to check if a device serial is that of a test device
function TestDeviceCheck($DB, $Serial)
{
    #Default to false
    $return = false;
    $MySQLPull = mysqli_query($DB,
        "SELECT `Serial` FROM `ota-test_devices` ORDER BY `ID`;");
    while ($daserial = mysqli_fetch_array($MySQLPull)) {
        if ($daserial['Serial'] == $Serial) {
            $return = true;
            break;
        }
    }
    return $return;
}
# Used to see if we are on a dev firmware or not
function TestBuildCheck($DB, $Build)
{
    #Default to false
    $return = false;
    $MySQLPull = mysqli_query($DB,
        "SELECT * FROM `ota-available_updates` WHERE `Version`=$Build ORDER BY `ID` LIMIT 1;");
    if (mysqli_num_rows($MySQLPull)) {
        while ($daver = mysqli_fetch_array($MySQLPull)) {
            if ($daver['TestBuild'] == "1") {
                $return = true;
                break;
            }
        }
    } else {
        # If build is not documented, it is probably a test release
        $return = true;
    }
    return $return;
}

# Used to pull the database timestamp if it exists.
function WhitelistTimestamp($Database, $Prefix, $TestDev)
{
    if ($TestDev == true) {
        $CacheTimestampCall = mysqli_query($Database, "SELECT `timestamp` FROM `$Prefix-cache` WHERE `test_list`=1 ORDER BY `ID` DESC LIMIT 1;");
    } else {
        $CacheTimestampCall = mysqli_query($Database, "SELECT `timestamp` FROM `$Prefix-cache` WHERE `test_list`=0 ORDER BY `ID` DESC LIMIT 1;");
    }
    # If a entry exists pull its timestamp, otherwise return 0
    if (mysqli_num_rows($CacheTimestampCall) == "1") {
        $CacheTimestampRaw = mysqli_fetch_assoc($CacheTimestampCall);
        return $CacheTimestampRaw["timestamp"];
    } else {
        return "0";
    }
}

# Used to pull the whitelist file contents, echo out as var
# This does NOT include file headers
function PullWhitelist($Database, $Prefix, $TestDev)
{
    # Pull from cache
    if ($TestDev == true) {
        $CacheListRaw = mysqli_fetch_assoc(mysqli_query($Database,
            "SELECT `content` FROM `$Prefix-cache` WHERE `test_list`=1 ORDER BY `ID` DESC LIMIT 1;"));
    } else {
        $CacheListRaw = mysqli_fetch_assoc(mysqli_query($Database,
            "SELECT `content` FROM `$Prefix-cache` WHERE `test_list`=0 ORDER BY `ID` DESC LIMIT 1;"));
    }

    #Return Response
    return $CacheListRaw["content"];
}

# Used to sync the whitelist with google
function UpdateWhitelist($Database, $Prefix, $ProviderURL, $TestDev)
{
    # First we add our applications from the database to the applist
    if ($TestDev == true) {
        $PullApps = mysqli_query($Database, "SELECT `content` FROM `$Prefix-custom_apps` WHERE `v2app`=0 ORDER BY `ID`;");
    } else {
        $PullApps = mysqli_query($Database, "SELECT `content` FROM `$Prefix-custom_apps` WHERE `v2app`=0 AND `test_app`=0 ORDER BY `ID`;");
    }

    # For each app, do a loop into an array
    $i = 0;
    while ($da_apps = mysqli_fetch_array($PullApps)) {
        $CustomApps[$i] = $da_apps['content'];
        $i++;
    }

    # Did we have any apps to add?
    if ($i != 0) {
        #Now we format our apps into a single variable
        $AddedApps = ''; # Does this need to be defined as empty first?
        foreach ($CustomApps as $CustomAppSingle) {
            $AddedApps .= $CustomAppSingle . ",";
        }
    } else {
        $AddedApps = "";
    }

    # Now same process as above, but for v2 apps because google.
    # First we add our applications from the database to the applist
    if ($TestDev == true) {
        $PullAppsv2 = mysqli_query($Database, "SELECT `name` FROM `$Prefix-custom_apps` WHERE `v2app`=1 ORDER BY `ID`;");
    } else {
        $PullAppsv2 = mysqli_query($Database, "SELECT `name` FROM `$Prefix-custom_apps` WHERE `v2app`=1 AND `test_app`=0 ORDER BY `ID`;");
    }

    # For each app, do a loop into an array
    $i = 0;
    while ($da_appsv2 = mysqli_fetch_array($PullAppsv2)) {
        $CustomAppsv2[$i] = $da_appsv2['name'];
        $i++;
    }

    # Did we have any apps to add?
    if ($i != 0) {
        #Now we format our apps into a single variable
        $AddedAppsv2 = ''; # Does this need to be defined as empty first?
        foreach ($CustomAppsv2 as $CustomAppSinglev2) {
            $AddedAppsv2 .= "\"" . $CustomAppSinglev2 . "\",";
        }
    } else {
        $AddedAppsv2 = "";
    }

    # pull everything from google
    $GoogleList = file_get_contents($ProviderURL);

    # If dev device, change to dev idle screen
    if ($TestDev == true) {
        $GoogleList = preg_replace('/\"idle_screen_app\":\"00000000-0000-0000-0000-000000000000\"/',
            '"idle_screen_app":"TeamEureka-Idlescreen-Dev"', $GoogleList);
    }

    # Add our apps
    $OurList = substr_replace($GoogleList, $AddedApps, 22, 0);

    # Now add our v2 apps
    $OurList = preg_replace('/\"enabled_app_ids\":\[/', '"enabled_app_ids":[' . $AddedAppsv2,
        $OurList);

    # Update database cache, or create if needed
    $NewTime = time();
    $OurListClean = mysqli_real_escape_string($Database, $OurList);

    # Set the cache type
    if ($TestDev == true) {
        $CacheType = "1";
    } else {
        $CacheType = "0";
    }

    # Before we create a record, we check if the last record has the same content.
    # If so, just update its timestamp to save space.
    # We use MD5 to check if a change is needed, must be of non clean ver otherwise hash is always diff
    $CachedVer = PullWhitelist($Database, $Prefix, $TestDev);
    if (md5($CachedVer) == md5($OurList)) {
        mysqli_query($Database, "UPDATE `$Prefix-cache` SET `timestamp`='$NewTime' WHERE `content`='$OurListClean'");
    } else {
        mysqli_query($Database, "INSERT INTO `$Prefix-cache` (`id`, `test_list`, `timestamp`, `content`) VALUES (null, '$CacheType', '$NewTime', '$OurListClean');");
    }
}

# Used to convert the format of the whitelist response to
# meet the requrements of different ROM versions
# We just do this in real time as its pointless to cache multiple versions
function ConvertWhitelist($Whitelist, $ReportedVersion)
{
    # If a version needs modifications, do it vi a if catch
    if ($ReportedVersion <= "13300.999") {
        $Whitelist = preg_replace('/app_id\b/', 'app_name', $Whitelist); # Change app_id to app_name
        $Whitelist = preg_replace('/,\"dial_enabled\":true\b/', "", $Whitelist); # Remove dial_enabled
        $Whitelist = preg_replace('/,"enabled_app_ids(.*?)]/', "", $Whitelist); # Remove all enabled_app_ids
    }

    # Support old allcast app on old builds
    if ($ReportedVersion < "15250.001") {
        $Whitelist = preg_replace('/www.gstatic.com\/cv\/receiver1.html/',
            'www.gstatic.com/cv/versions/release-d4fa0a24f89ec5ba83f7bf3324282c8d046bf612/receiver.html',
            $Whitelist);
    }

    # Return the Whitelist
    return $Whitelist;
}

# used to lookup custom v2 apps from the database
function AppLookup($Database, $Prefix, $AppID, $TestDev)
{

    # Set time vars for function
    $Time = time();
    $OldTime = strtotime("-12 hours", $Time);

    # First lets check the custom apps, this requires knowing if we have a test device
    if ($TestDev == true) {
        $AppRawCus = mysqli_fetch_assoc(mysqli_query($Database, "SELECT `content` FROM `$Prefix-custom_apps` WHERE `v2app`=1 AND `NAME`='$AppID' ORDER BY `ID` DESC LIMIT 1;"));
    } else {
        $AppRawCus = mysqli_fetch_assoc(mysqli_query($Database, "SELECT `content` FROM `$Prefix-custom_apps` WHERE `test_app`=0 AND `v2app`=1 AND `NAME`='$AppID' ORDER BY `ID` DESC LIMIT 1;"));
    }

    # Did we have any results?
    if ($AppRawCus["content"] == null) {
        # If not, check cache for a app config that is not more than 24 hours old.
        # We do this to prevent our database from filling up. Need to implement MD5 checking like we have for the normal whitelist cache.
        $AppRaw = mysqli_fetch_assoc(mysqli_query($Database,
            "SELECT `content`,`timestamp` FROM `$Prefix-cache-v2apps` WHERE `name`='$AppID' ORDER BY `ID` DESC LIMIT 1"));

        # Is app in database?
        if ($AppRaw['content'] == null) {
            # App is not in database
            # Does google have it? if so, cache result.
            $GoogleLookup = file_get_contents("http://clients3.google.com/cast/chromecast/device/app?a=$AppID");
            if ($GoogleLookup) {
                $GoogleLookup = substr($GoogleLookup, 5);
                mysqli_query($Database, "INSERT INTO `$Prefix-cache-v2apps` (`id`, `name`, `timestamp`, `content`) VALUES (null, '$AppID', '$Time', '$GoogleLookup')");
                $Return = $GoogleLookup;
            } else {
                # No luck? Return 404 error.
                $Return = "Error";
            }
        } else {
            #App is in database
            # Is it outdated?
            if ($AppRaw['timestamp'] < $OldTime) {
                # Update Database Copy
                # Does google have it? if so, cache result.
                $GoogleLookup = file_get_contents("http://clients3.google.com/cast/chromecast/device/app?a=$AppID");
                if ($GoogleLookup) {
                    $GoogleLookup = substr($GoogleLookup, 5);
                    mysqli_query($Database, "UPDATE `$Prefix-cache-v2apps` SET `timestamp`='$Time', `content`='$GoogleLookup' WHERE `name`='$AppID'");
                    $Return = $GoogleLookup;
                } else {
                    # No luck? Return 404 error.
                    $Return = "Error";
                }
            } else {
                # Push Database Ver
                $Return = $AppRaw["content"];
            }
        }
    } else {
        # Was in custom_apps, return results
        $Return = $AppRawCus["content"];
    }
    #Return Response
    return $Return;
}
# Now we actually do things

# Check if a Revision was set from the whitelist update call
# DO NOT change this, as of 15250.001 devices now report version during sync
if (!isset($_GET['version'])) {
    $ReportedRev = "14975.001";
} else {
    $ReportedRev = $_GET['version'];
}

# We use the serial to check if a device is a test device or not
if (!isset($_GET['serial'])) {
    $IsDevDevice = false;
} else {
    # Are we a test device, and is this a unreleased build?
    if (TestDeviceCheck($DBcon, mysqli_real_escape_string($DBcon, $_GET['serial'])) == true &&
        TestBuildCheck($DBcon, $ReportedRev) == true) {
        $IsDevDevice = true;
    } else {
        $IsDevDevice = false;
    }
}

# Find out early, is this a v2 app lookup, or a whitelist pull?
if (isset($_GET['applookup'])) {
    # Do the lookup
    # Was a app ID defined for lookup?
    if (isset($_GET['a'])) {
        $ReturnApp = AppLookup($DBcon, $DBprefix, mysqli_real_escape_string($DBcon, $_GET['a']),
            $IsDevDevice);
    } else {
        #no app being lookedup
        header("HTTP/1.0 400 Bad Request");
        exit();
    }

    # Do error check first
    if ($ReturnApp == "Error") {
        header("HTTP/1.0 404 Not Found");
        exit();
    }

    # Print Response
    if (!isset($_GET['devmode'])) {
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Cache-Control: public");
        header('Content-Type: application/json');
        header("Content-Description: File Transfer");
        header('Content-Disposition: attachment; filename=\'json.txt\'');
        header("Content-Transfer-Encoding: binary\n\n\n");
    }
    # Lets output the app info then
    echo ")]}'\n" . $ReturnApp;
} else {
    # Do we update cache, or no?
    # Update every 6 hours
    if (WhitelistTimestamp($DBcon, $DBprefix, $IsDevDevice) <= strtotime("-6 hours",
        time())) {
        UpdateWhitelist($DBcon, $DBprefix, $GooglesURL, $IsDevDevice);
    }

    # Pull Whitelist
    $ReturnWhitelist = PullWhitelist($DBcon, $DBprefix, $IsDevDevice);

    # Convert if Required
    $ReturnWhitelist = ConvertWhitelist($ReturnWhitelist, $ReportedRev);

    # Set headers, dev mode does not
    if (!isset($_GET['devmode'])) {
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Cache-Control: public");
        header("Content-Description: File Transfer");
        header('Content-Disposition: attachment; filename=\'apps.conf\'');
        header("Content-Transfer-Encoding: binary\n\n\n");
    }

    # output the final whitelist to the device
    echo $ReturnWhitelist;
}
?>