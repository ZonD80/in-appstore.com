<?php
/*
 in-app-proxy
 Copyright ZonD80
 Attribution-NonCommercial 3.0 Unported (CC BY-NC 3.0)
 */

//die('DISABLED');
define('PROXY', false); // if false, emulation, if true acts as proxy

date_default_timezone_set('UTC');

session_start();
//ini_set('error_reporting',E_ALL);
//ini_set('display_errors',true);

function curl_request($url, $post_data = array(), $user_agent = '')
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    if ($user_agent) {
        curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
    }
    if ($post_data) {
        $post_data = http_build_query($post_data, '', '&');

        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    return curl_exec($ch);
}

function NS_to_array($data)
{
    preg_match_all('#"(.*?)" \= "(.*?)"\;#si', $data, $matches);
    foreach ($matches[1] as $key => $match) {
        $return[$match] = $matches[2][$key];
    }
    return $return;
}

function array_to_NS($ar)
{
    $return = array();
    foreach ($ar as $k => $v) {
        $return[] = "\t\"$k\" = \"$v\"";
    }
    return "{\n" . implode(";\n", $return) . ";\n}";
}

if (!function_exists('getallheaders')) {
    function getallheaders()
    {
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

function write_log($file, $line)
{

    if ($_SERVER['REMOTE_ADDR'] == '185.46.154.54') {
        fwrite($file, $line);
    }
}

$OWNHEADERS = false;
//if ($_SERVER['HTTP_X_APPLE_CLIENT_APPLICATION']||$_SERVER['HTTP_HOST']=='se.itunes.apple.com') die('<h1>Hi, dude!</h1><p style="color:green;">You <b>connected</b> to in-appstore.com, but...</p><p style="color:red;">Looks like you are using AppStore client. Please use <b>application itself</b> or remove DNS setting if you want to use AppStore client.</p><p><b>REMEMBER, THAT IN-APPSTORE.COM IS ONLY FOR LEGALLY PURCHASED APPS!</b></p>');
$fpath = '../in-appstore.com/iapcracker.txt';

$logpath = 'log.txt';

$file = fopen($logpath, 'a+'); // uncomment all write_log and fopen/fclose to allow logging

$db = array(
    'host' => 'localhost',
    'user' => 'dababase user',
    'pass' => 'database password',
    'db' => 'database',
    'charset' => 'utf8'
);

//require_once ('classes/database.class.php');

//$DB = new DB($db);

unset($db);

function getval($name, $type = 'string')
{
    if ($_GET[$name]) {
        $t = $_GET[$name];
    } else
        $t = $_POST[$name];
    eval('$t = (' . $type . ')$t;');
    return $t;
}

function gunzip($zipped)
{
    $offset = 0;
    if (substr($zipped, 0, 2) == "\x1f\x8b")
        $offset = 2;
    if (substr($zipped, $offset, 1) == "\x08") {
        # file_put_contents("tmp.gz", substr($zipped, $offset - 2));
        return gzinflate(substr($zipped, $offset + 8));
    }
    return "Unknown Format";
}

require_once('classes/plist.php');
$parser = new plistParser();

$text = '';
$uri = (string)$_GET['URI'];
$server = var_export($_SERVER, true);
$post = var_export($_POST, true);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $METHOD = 'POST';
    if (preg_match('/authenticate/',$_SERVER['REQUEST_URI']) || preg_match('/inAppBuy/', $_SERVER['REQUEST_URI']) || preg_match('/verifyReceipt/', $_SERVER['REQUEST_URI'])) $POST_CONTENT = http_get_request_body(); else
        $POST_CONTENT = http_build_query($_POST);
    $POST_CONTENT = urlencode(http_get_request_body());
    write_log($file, "\n\nPOST RAW: " . var_export(http_get_request_body(), true) . "\n\n\n");
    $text = '!!!NOW POST!!!';
} else $METHOD = 'GET';


//var_dump($headers);

$url = "http" . ($_SERVER['SERVER_PORT'] == 443 ? 's' : '') . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$urlInfo = @parse_url($url);

$http_path = $urlInfo['path'];
$http_host = $urlInfo['host'];

/*if (preg_match('/fetchSoftwareAddOns//', $_SERVER['REQUEST_URI'])) {
    $plist = $parser->parseString(gunzip(file_get_contents($url)));
    var_dump($plist);
    die();
}*/

if (!PROXY) {
    if (preg_match('/offerAvailabilityAndInfoDialog/', $_SERVER['REQUEST_URI'])) {
        $to_db_get = explode(',', 'restrictionLevel,id,versionId,guid,quantity,offerName,lang,bid,bvrs,icuLocale');
        foreach ($to_db_get as $gv) {
            if ($gv == 'id') $key = 'salableadamid'; else $key = $gv;
            $to_db[$key] = getval($gv);

            if (in_array($gv, explode(',', 'restrictionLevel,lang,icuLocale,guid'))) continue;
            elseif ($gv == 'versionId') $key = 'appExtVrsId';
            elseif ($gv == 'id') $key = 'appAdamId';
            else $key = $gv;
            $to_plist[$key] = getval($gv);
        }

        /// here salableadamid checks
        $app_adamids = array(
            'com.zeptolab.ctrexperiments' => 534185042,
            'com.ea.fca.inc' => 516027964,
            "com.sega.SangokushiConquest" => 492200219,
            "com.firemint.flightcontrolipad" => 363727129,
            "ru.mail.jugger" => 512970482,
            "com.fullfat.ios.agentdash" => 540410480
        );
        //
        $to_plist['salableAdamId'] = (array_key_exists($to_db['bid'], $app_adamids) ? $app_adamids[$to_db['bid']] : '1');
        $to_plist['productType'] = 'A';
        $to_plist['price'] = '1';
        $to_plist['pricingParameters'] = 'STDQ';
        //$DB->query("INSERT INTO `cache` " . $DB->build_insert_query($to_db));
    }
}
if ($_SERVER['SERVER_PORT'] == 443) $text .= ' !!! HTTPS'; else $text .= " !!! PLAIN";

$get = var_export($_GET, true);

$headers = getallheaders();

$text .= "\n\nuri:$uri\n\nserver:$server\n\n\nget:$get\n\n\npost:$post\n\npost_content:" . var_export($POST_CONTENT, true) . "\n\noriginal_headers:" . var_export($headers, true) . "\n\n";


if (preg_match('/authenticate/', $_SERVER['REQUEST_URI'])) { // write now
    if (PROXY) {
        write_log($file, 'USING CURL!!!!!!!!');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        curl_setopt($ch, CURLOPT_COOKIEFILE, '_cookie.txt');
        curl_setopt($ch, CURLOPT_COOKIEJAR, '_cookie.txt');

        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        foreach ($headers AS $hkey => $hval) {
            // if ($hkey == 'Accept-Encoding') continue;
            //if ($hkey == 'Host') $hval = $_SERVER['HTTP_HOST'];
            // if ($hkey == 'Connection') $hval = 'close';
            if (in_array($hkey, explode(',', 'Cookie,Host,Accept-Language,X-Apple-Store-Front')))
                $ha[] = "$hkey: $hval";
            // }
        }

        //$ha[] = 'Expect: 100-continue';

        write_log($file, "\n\nREQUEST HEADERS:" . var_export($ha, true));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $ha);
        curl_setopt($ch, CURLOPT_URL, $url);
        //curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $POST_CONTENT);
        $result2 = curl_exec($ch);

        $CURL = true;
    } else {
        $plist = $parser->parseString(urldecode($POST_CONTENT));

        //session_start();
        //$_SESSION['appleId'] = $plist['appleId'];

        $result2 = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<!DOCTYPE plist PUBLIC "-//Apple Computer//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">

  <plist version="1.0">
    <dict>








        <key>accountInfo</key>
        <dict>
    <key>appleId</key><string>' . $plist['appleId'] . '</string>
    <key>accountKind</key><string>0</string>
    <key>address</key>
    <dict>
      <key>firstName</key><string>John</string>
      <key>lastName</key><string>Appleseed</string>
    </dict>
  </dict>
        <key>passwordToken</key><string>38E551613891422919A2326B3AE8EB' . rand(0, 16) . '</string>
        <key>clearToken</key><string>303030303030313236383135333532' . rand(0, 32) . '</string>

        <key>is-cloud-enabled</key><string>false</string>

        <key>dsPersonId</key><string>' . $_SERVER['HTTP_X_DSID'] . '</string>
<key>creditDisplay</key><string></string>

<key>creditBalance</key><string>1311811</string>
<key>freeSongBalance</key><string>1311811</string>







    <key>status</key><integer>0</integer>





















    </dict>
  </plist>



';
        $OWNHEADERS = true;
    }
} else {
    $h = '';

    foreach ($headers AS $hkey => $hval) {
        // if ($hkey=='Cookie') continue;
        if ($hkey == 'Content-Length' && $METHOD == 'POST') $hval = strlen($POST_CONTENT);
        if ($hkey == 'Host') $hval = $_SERVER['HTTP_HOST'];
        if ($hkey == 'Connection') $hval = 'close';

        $h .= "$hkey: $hval\r\n";
        //}
    }
    $opts = array('http' =>
        array(
            'method' => $METHOD,
            'header' => $h,
            'protocol_version' => '1.1',
            'timeout' => 3,
            'content' => $POST_CONTENT
            //'Accept: text/html, image/gif, image/jpeg, *; q=.2, */*; q=.2',
        )
    );

    $text .= "context_options:" . var_export($opts, true) . "\n\nrequest_headers:" . var_export($h, true) . "\n\n";


    $context = stream_context_create($opts);
    // THIS LINE IS VERY, VERY, EXTREMELY IMPORTANT, I SPENT OVER $300 TO CODE IT!
    if (PROXY) $result = file_get_contents($url, false, $context); // UNCOMMENT THIS TO FETCH PURCHUASE LISTS FROM APPSTORE
    else {
        if (!preg_match('/inAppBuy/', $_SERVER['REQUEST_URI']) && !preg_match('/inAppTransactionDone/', $_SERVER['REQUEST_URI']) && !preg_match('/verifyReceipt/', $_SERVER['REQUEST_URI']) && !preg_match('/offerAvailabilityAndInfoDialog/', $_SERVER['REQUEST_URI']) && !preg_match('/verifyReceipt/', $_SERVER['REQUEST_URI']) && !preg_match('/inAppCheckRecurringDownloadQueue/', $_SERVER['REQUEST_URI']))
            $result = '';//$result = file_get_contents($url, false, $context); // UNCOMMENT THIS TO FETCH PURCHUASE LISTS FROM APPSTORE
        else {
            $result = '';
            $OWNHEADERS = true;
        }
    }
    write_log($file, "\n\nREQUEST-RESULT:$url " . var_export((!preg_match('/inAppBuy/', $_SERVER['REQUEST_URI']) && !preg_match('/inAppTransactionDone/', $_SERVER['REQUEST_URI']) && !preg_match('/verifyReceipt/', $_SERVER['REQUEST_URI'])), true) . var_export(gunzip($result), true) . "\n\n");
}

write_log($file, $text);
$text = '';
if (!$CURL && !$OWNHEADERS) $result2 = gunzip($result); // else $result2=$result;

if (!PROXY) {
    if (preg_match('/MZPurchaseDaap.woa/',$_SERVER['REQUEST_URI'])) {
        $result2 = 'msrv    meds     msdc      msli   aeCP   aeSX         mslr    mpro     apro     msas   msed    mspi    ated    mstm       msii    mstt      È';
    }
    elseif (preg_match('/authorizeMachine/',$_SERVER['REQUEST_URI'])) {
        $result2 = '<!DOCTYPE plist PUBLIC "-//Apple Computer//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
	<dict>
		<key>jingleDocType</key>
		<string>machineAuthorizationInfoSuccess</string>
		<key>jingleAction</key>
		<string>authorizeMachine</string>
		<key>status</key>
		<integer>0</integer>
	</dict>
</plist>';
    }
    elseif (preg_match('/MZDSService.woa/',$_SERVER['REQUEST_URI'])) {
        //session_start();
        $result2 = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<!DOCTYPE plist PUBLIC "-//Apple Computer//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
  <key>status</key><integer>0</integer>
  <key>appleid</key><string>in-appstore.com</string>
</dict>
</plist>
';
    }
    elseif (preg_match('/inAppCheckRecurringDownloadQueue/', $_SERVER['REQUEST_URI'])) {

        $result2 = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<!DOCTYPE plist PUBLIC "-//Apple Computer//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">

  <plist version="1.0">
    <dict>






  <key>jingleDocType</key><string>inAppSuccess</string>
  <key>jingleAction</key><string>inAppPendingTransactions</string>
  <key>dsid</key><string>' . $_SERVER['HTTP_X_DSID'] . '</string>




    <key>download-queue-item-count</key><integer>0</integer>





















    </dict>
  </plist>



';
    } elseif (preg_match('/offerAvailabilityAndInfoDialog/', $_SERVER['REQUEST_URI'])) {
        // write_log($file, "\n\nPARSED_RESPONSE_AVAILABILITY:" . var_export($parser->parseString($result2), true));


        $words = array('Vodka', 'Bears', 'Matryoshka', 'Ushanka', 'Balalaika', 'Samovar');
        $result2 = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<!DOCTYPE plist PUBLIC "-//Apple Computer//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">

  <plist version="1.0">
    <dict>






  <key>jingleDocType</key><string>inAppSuccess</string>
  <key>jingleAction</key><string>offerAvailabilityAndInfoDialog</string>
  <key>dsid</key><string></string>








      <key>dialog</key>
      <dict>


    <key>message</key><string>Do you like in-appstore.com?</string>
    <key>explanation</key><string>"' . $to_db['bid'] . '"' . "\n\n" . 'Tap "LIKE" to like. Enter random credentials on auth popup.</string>
    <key>defaultButton</key><string>Buy</string>


    <key>okButtonString</key><string>LIKE</string>
    <key>okButtonAction</key><dict>

    <key>kind</key><string>Buy</string>
    <key>buyParams</key><string>' . str_replace('&', '&amp;', http_build_query($to_plist)) . '</string>
    <key>itemName</key><string>' . $to_plist['offerName'] . '</string>















</dict>


    <key>cancelButtonString</key><string>' . $words[array_rand($words)] . '!</string>









</dict>


















    </dict>
  </plist>



';
        /*$replacements = $parser->parseString($result2);

        /*$bpsql = $DB->sqlesc($replacements['dialog']['okButtonAction']['buyParams']);
        $bp = $DB->query_row("SELECT id,invalid FROM bps WHERE bp=$bpsql");
        $reportnumber = $bp['id'];
        $invalid_purchase = $bp['invalid'];
        if (!$reportnumber) {

            $DB->query("INSERT IGNORE INTO bps (bp) VALUES (" . $bpsql . ")");
            $reportnumber = mysql_insert_id();
        }

        $reportnumber = 'SAFE_MODE';

        if ($invalid_purchase) {
            $result2 = str_replace($replacements['dialog']['message'], 'Like in-appstore.com?', $result2);
            $result2 = str_replace($replacements['dialog']['explanation'], "This purchase ($reportnumber) reported as invalid.\nIt means that this application requires signed by apple receipt.\n Would you like to really buy this in-app feature via AppStore?\n It will be cached for you to receive this in-app for free in future.", $result2);
            $result2 = str_replace("<key>okButtonString</key><string>{$replacements['dialog']['okButtonString']}</string>","<key>okButtonString</key><string>$$$</string>", $result2);

        } else {
            $result2 = str_replace($replacements['dialog']['message'], 'Like in-appstore.com?', $result2);
            $result2 = str_replace($replacements['dialog']['explanation'], "If purchuase fails, report this number ($reportnumber) to http://www.in-appstore.com/p/report-about-failed-purchases-here.html.\n If you like in-appstore.com, tap LIKE button!", $result2);
            $result2 = str_replace("<key>okButtonString</key><string>{$replacements['dialog']['okButtonString']}</string>","<key>okButtonString</key><string>LIKE</string>", $result2);
        }
        $result2 = str_replace($replacements['dialog']['cancelButtonString'], 'Nope', $result2);
    */

    } elseif (preg_match('/inAppBuy/', $_SERVER['REQUEST_URI'])) {
        // get purchase

        $plist = $parser->parseString(urldecode($POST_CONTENT));

        // $guid = $matches[0][0];
        write_log($file, "\n\n\nMATCHES " . var_export($plist, true));

        $to_transactions = array(
            'item-id' => $plist['salableAdamId'],
            'app-item-id' => $plist['appAdamId'],
            'version-external-identifier' => $plist['appExtVrsId'],
            'bid' => $plist['bid'],
            'bvrs' => $plist['bvrs'],
            'offer-name' => $plist['offerName'],
            'quantity' => $plist['quantity']

        );

        $transid = rand(0, 1800000);

        // apps that require valid receipts

        $custom_valid_receipt_required = array(
            "com.iconology",
            "com.zinio",
            "com.zeptolab",
            "com.futurenet",
            "com.disney",
            "com.gamevil",
            "com.appyentertainment",
            "ch.zattoo",
            "com.teamlava",
            "com.backpacker",
            "com.kalmbach",
            "com.pixelImages",
            "com.thedaily",
            "ubi.084",
            "com.ea",
            "com.firemint",
            "com.ndemiccreations",
            "com.gameloft",
            "com.utw",
            "com.kongzhong",
            "com.funzio",
            "com.sega",
            "ru.mail",
            "com.fullfat",
            "pl.presspublica",
            "com.paperlit",
            "pl.m2a.echodnia",
            "com.gsmchoice.Angora",
            "pl.przekroj.przekrojipad",
            "pl.pb.pulsPl",
            "pl.presspublica.Politykahd",
            "pl.agora.agorareader",
            "com.bodunov",
            "com.PixelAddicts"
        );

        if (!preg_match("/(" . implode('|', str_replace('.', '\.', $custom_valid_receipt_required)) . ")/", $plist['bid'])) {

            $purchase_info = array(
                'original-purchase-date-pst' => date('Y-m-d H:i:s', time() - 7 * 3600) . ' America/Los_Angeles',
                'purchase-date-ms' => time() . '000',
                'original-transaction-id' => $transid,
                'bvrs' => $plist['bvrs'],
                'app-item-id' => $plist['appAdamId'],
                'transaction-id' => $transid,
                'quantity' => $plist['quantity'],
                'original-purchase-date-ms' => time() . '000',
                'item-id' => $plist['salableAdamId'],
                'version-external-identifier' => $plist['appExtVrsId'],
                'product-id' => $plist['offerName'],
                'purchase-date' => date('Y-m-d H:i:s') . ' Etc/GMT',
                'original-purchase-date' => date('Y-m-d H:i:s') . ' Etc/GMT',
                'bid' => $plist['bid'],
                'purchase-date-pst' => date('Y-m-d H:i:s', time() - 7 * 3600) . ' America/Los_Angeles'//,
                // "environment" = "Sandbox"
            );


            $receipt_data = 'ewoJInNpZ25hdHVyZSIgPSAiQXBkeEpkdE53UFUyckE1L2NuM2tJTzFPVGsyNWZlREthMGFhZ3l5UnZlV2xjRmxnbHY2UkY2em5raUJTM3VtOVVjN3BWb2IrUHFaUjJUOHd5VnJITnBsb2YzRFgzSXFET2xXcSs5MGE3WWwrcXJSN0E3ald3dml3NzA4UFMrNjdQeUhSbmhPL0c3YlZxZ1JwRXI2RXVGeWJpVTFGWEFpWEpjNmxzMVlBc3NReEFBQURWekNDQTFNd2dnSTdvQU1DQVFJQ0NHVVVrVTNaV0FTMU1BMEdDU3FHU0liM0RRRUJCUVVBTUg4eEN6QUpCZ05WQkFZVEFsVlRNUk13RVFZRFZRUUtEQXBCY0hCc1pTQkpibU11TVNZd0pBWURWUVFMREIxQmNIQnNaU0JEWlhKMGFXWnBZMkYwYVc5dUlFRjFkR2h2Y21sMGVURXpNREVHQTFVRUF3d3FRWEJ3YkdVZ2FWUjFibVZ6SUZOMGIzSmxJRU5sY25ScFptbGpZWFJwYjI0Z1FYVjBhRzl5YVhSNU1CNFhEVEE1TURZeE5USXlNRFUxTmxvWERURTBNRFl4TkRJeU1EVTFObG93WkRFak1DRUdBMVVFQXd3YVVIVnlZMmhoYzJWU1pXTmxhWEIwUTJWeWRHbG1hV05oZEdVeEd6QVpCZ05WQkFzTUVrRndjR3hsSUdsVWRXNWxjeUJUZEc5eVpURVRNQkVHQTFVRUNnd0tRWEJ3YkdVZ1NXNWpMakVMTUFrR0ExVUVCaE1DVlZNd2daOHdEUVlKS29aSWh2Y05BUUVCQlFBRGdZMEFNSUdKQW9HQkFNclJqRjJjdDRJclNkaVRDaGFJMGc4cHd2L2NtSHM4cC9Sd1YvcnQvOTFYS1ZoTmw0WElCaW1LalFRTmZnSHNEczZ5anUrK0RyS0pFN3VLc3BoTWRkS1lmRkU1ckdYc0FkQkVqQndSSXhleFRldngzSExFRkdBdDFtb0t4NTA5ZGh4dGlJZERnSnYyWWFWczQ5QjB1SnZOZHk2U01xTk5MSHNETHpEUzlvWkhBZ01CQUFHamNqQndNQXdHQTFVZEV3RUIvd1FDTUFBd0h3WURWUjBqQkJnd0ZvQVVOaDNvNHAyQzBnRVl0VEpyRHRkREM1RllRem93RGdZRFZSMFBBUUgvQkFRREFnZUFNQjBHQTFVZERnUVdCQlNwZzRQeUdVakZQaEpYQ0JUTXphTittVjhrOVRBUUJnb3Foa2lHOTJOa0JnVUJCQUlGQURBTkJna3Foa2lHOXcwQkFRVUZBQU9DQVFFQUVhU2JQanRtTjRDL0lCM1FFcEszMlJ4YWNDRFhkVlhBZVZSZVM1RmFaeGMrdDg4cFFQOTNCaUF4dmRXLzNlVFNNR1k1RmJlQVlMM2V0cVA1Z204d3JGb2pYMGlreVZSU3RRKy9BUTBLRWp0cUIwN2tMczlRVWU4Y3pSOFVHZmRNMUV1bVYvVWd2RGQ0TndOWXhMUU1nNFdUUWZna1FRVnk4R1had1ZIZ2JFL1VDNlk3MDUzcEdYQms1MU5QTTN3b3hoZDNnU1JMdlhqK2xvSHNTdGNURXFlOXBCRHBtRzUrc2s0dHcrR0szR01lRU41LytlMVFUOW5wL0tsMW5qK2FCdzdDMHhzeTBiRm5hQWQxY1NTNnhkb3J5L0NVdk02Z3RLc21uT09kcVRlc2JwMGJzOHNuNldxczBDOWRnY3hSSHVPTVoydG04bnBMVW03YXJnT1N6UT09IjsKCSJwdXJjaGFzZS1pbmZvIiA9ICJld29KSW05eWFXZHBibUZzTFhCMWNtTm9ZWE5sTFdSaGRHVXRjSE4wSWlBOUlDSXlNREV5TFRBM0xURXlJREExT2pVME9qTTFJRUZ0WlhKcFkyRXZURzl6WDBGdVoyVnNaWE1pT3dvSkluQjFjbU5vWVhObExXUmhkR1V0YlhNaUlEMGdJakV6TkRJd09UYzJOelU0T0RJaU93b0pJbTl5YVdkcGJtRnNMWFJ5WVc1ellXTjBhVzl1TFdsa0lpQTlJQ0l4TnpBd01EQXdNamswTkRrME1qQWlPd29KSW1KMmNuTWlJRDBnSWpFdU5DSTdDZ2tpWVhCd0xXbDBaVzB0YVdRaUlEMGdJalExTURVME1qSXpNeUk3Q2draWRISmhibk5oWTNScGIyNHRhV1FpSUQwZ0lqRTNNREF3TURBeU9UUTBPVFF5TUNJN0Nna2ljWFZoYm5ScGRIa2lJRDBnSWpFaU93b0pJbTl5YVdkcGJtRnNMWEIxY21Ob1lYTmxMV1JoZEdVdGJYTWlJRDBnSWpFek5ESXdPVGMyTnpVNE9ESWlPd29KSW1sMFpXMHRhV1FpSUQwZ0lqVXpOREU0TlRBME1pSTdDZ2tpZG1WeWMybHZiaTFsZUhSbGNtNWhiQzFwWkdWdWRHbG1hV1Z5SWlBOUlDSTVNRFV4TWpNMklqc0tDU0p3Y205a2RXTjBMV2xrSWlBOUlDSmpiMjB1ZW1Wd2RHOXNZV0l1WTNSeVltOXVkWE11YzNWd1pYSndiM2RsY2pFaU93b0pJbkIxY21Ob1lYTmxMV1JoZEdVaUlEMGdJakl3TVRJdE1EY3RNVElnTVRJNk5UUTZNelVnUlhSakwwZE5WQ0k3Q2draWIzSnBaMmx1WVd3dGNIVnlZMmhoYzJVdFpHRjBaU0lnUFNBaU1qQXhNaTB3TnkweE1pQXhNam8xTkRvek5TQkZkR012UjAxVUlqc0tDU0ppYVdRaUlEMGdJbU52YlM1NlpYQjBiMnhoWWk1amRISmxlSEJsY21sdFpXNTBjeUk3Q2draWNIVnlZMmhoYzJVdFpHRjBaUzF3YzNRaUlEMGdJakl3TVRJdE1EY3RNVElnTURVNk5UUTZNelVnUVcxbGNtbGpZUzlNYjNOZlFXNW5aV3hsY3lJN0NuMD0iOwoJInBvZCIgPSAiMTciOwoJInNpZ25pbmctc3RhdHVzIiA9ICIwIjsKfQ==';
        } else {

            $purchase_info = array(
                'original-purchase-date-pst' => date('Y-m-d H:i:s', time() - 7 * 3600) . ' America/Los_Angeles',
                'purchase-date-ms' => time() . '000',
                'original-transaction-id' => $transid,
                'bvrs' => $plist['bvrs'],
                'app-item-id' => $plist['appAdamId'],
                'transaction-id' => $transid,
                'quantity' => $plist['quantity'],
                'original-purchase-date-ms' => time() . '000',
                'item-id' => $plist['salableAdamId'],
                'version-external-identifier' => $plist['appExtVrsId'],
                'product-id' => $plist['offerName'],
                'purchase-date' => date('Y-m-d H:i:s') . ' Etc/GMT',
                'original-purchase-date' => date('Y-m-d H:i:s') . ' Etc/GMT',
                'bid' => $plist['bid'],
                'purchase-date-pst' => date('Y-m-d H:i:s', time() - 7 * 3600) . ' America/Los_Angeles'//,
                // "environment" = "Sandbox"
            );
            $NSpurchase_info = array_to_NS($purchase_info);
            $purchase_info = base64_encode($NSpurchase_info);

            $private_key = '-----BEGIN PRIVATE KEY-----
MIICeAIBADANBgkqhkiG9w0BAQEFAASCAmIwggJeAgEAAoGBAM2mruqEJIG6TNlQ
906ht7+8Nd5Zas6ST0KC7bUQNM52wHQ0vj6XmljvL/cqul6N4bBQsFrwS7SNNZ/s
iChH/teyHOz87ia//NBbIMkKdVi7hoqAY+aSYX4g+kEiXcqVtdSnvb/Yao9+MIyo
GF6iKQ5TOFVT7/huLf71Y7N/ARiRAgMBAAECgYAwBRbc7eQ0YpMlP3Gv67UjUUhm
1gxJlgJp7nahC9q4xyPjPpmZtf61e4yAs3p3L7weVokHgwq6ayq1YB7fAQixW8X1
V2hiAC4gNPqktUdkkmZHqFsY16EJUPUiPdphP4lZcesp3zDYYJ7si+CH8FClRkTT
q1VENzqGGJBUKA7YkQJBAO98LYvJwDJxF2aqxM3HR2VhOs9rr+3oaSNxbbMLY1t9
qj8VqjAHBYX54ywqusCd2yco4meENTmHLGG3oGWNHZUCQQDb1TUOPxfEkIRkNibE
Nu2XNNFg7TgnE4oXeSXS+zphCW0EqJ+Y2kDbb16knH357KseX+G7aCPSl5PGkhUz
ZjgNAkEA00KTFy6JmrXC8/GPHQw/gkJMU+/mSZPtM7P7FqfkJTBs/6uH70gyaiav
bSXgisx2KExbtP+eyDnjP+xx1UOwJQJBAIgxTM9oszbqObtD+TxyszuMU3NzQ+ih
qFnmilJtprtbdZj/RvERtkC8fKwK79kYkOMej+DlIdxkX/8TneLcHzkCQQCWoZUC
kWXIN8JeWkfCDLOtf1+XdZ53n4jn+ciMzX7zu3FATHgr36tsNs7Q5bncoKLiefuN
U5tRKPm+9w0Bm+Pi
-----END PRIVATE KEY-----
';
            $pkeyid = openssl_get_privatekey($private_key);
// compute signature
            openssl_sign(chr(2) . $NSpurchase_info, $signature, $pkeyid);


            // free the key from memory
            openssl_free_key($pkeyid);
            $pucert = file_get_contents('pucert.cer');
            $to_receipt = array(
                'signature' => base64_encode(chr(2) . $signature . pack('N', strlen($pucert)) . $pucert),
                'purchase-info' => $purchase_info,
                'pod' => $_COOKIE['Pod'],
                'signing-status' => '0',
            );

            $receipt_data = base64_encode(array_to_NS($to_receipt));
        }

        //$DB->query("INSERT INTO `transactions` " . $DB->build_insert_query($to_transactions));

        if (preg_match('/MacAppStore/', $_SERVER['HTTP_USER_AGENT'])) {

            if (!$plist['generateBuyParams']) {
//                var_dump($plist);
                $result2 = '
<!DOCTYPE plist PUBLIC "-//Apple Computer//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
	<dict>
		<key>pings</key>
		<array>
			<string></string>
		</array>
		<key>jingleDocType</key>
		<string>inAppSuccess</string>
		<key>jingleAction</key>
		<string>inAppBuy</string>
		<key>dsid</key>
		<string />
		<key>download-queue-item-count</key>
		<integer>1</integer>
		<key>app-list</key>
		<array>
			<dict>
				<key>item-id</key>
				<integer>' . $plist['salableAdamId'] . '</integer>
				<key>app-item-id</key>
				<integer>' . $plist['appAdamId'] . '</integer>
				<key>version-external-identifier</key>
				<integer>' . $plist['appExtVrsId'] . '</integer>
				<key>bid</key>
				<string>' . $plist['bid'] . '</string>
				<key>bvrs</key>
				<string>' . $plist['bvrs'] . '</string>
				<key>offer-name</key>
				<string>' . $plist['offerName'] . '</string>
				<key>transaction-id</key>
				<string>'.$transid.'</string>
				<key>original-transaction-id</key>
				<string>'.$transid.'</string>
				<key>purchase-date</key>
				<date>' . date('Y-m-d\TH:i:s\Z') . '</date>
				<key>original-purchase-date</key>
				<date>' . date('Y-m-d\TH:i:s\Z') . '</date>
				<key>quantity</key>
				<integer>' . $plist['quantity'] . '</integer>
			</dict>
		</array>
		<key>receipt-data</key>
		<data>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</data>
		<key>dialog</key>
		<dict>
			<key>m-allowed</key>
			<false />
			<key>message</key>
			<string>Thank You</string>
			<key>explanation</key>
			<string>Your purchase was successful.</string>
			<key>defaultButton</key>
			<string>ok</string>
			<key>okButtonString</key>
			<string>OK</string>
		</dict>
	</dict>
</plist>';
               /* $result2 = '<!DOCTYPE plist PUBLIC "-//Apple Computer//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
	<dict>
		<key>pings</key>
		<array>
			<string></string>
		</array>
		<key>jingleDocType</key>
		<string>inAppSuccess</string>
		<key>jingleAction</key>
		<string>inAppBuy</string>
		<key>dsid</key>
		<string />
		<key>download-queue-item-count</key>
		<integer>1</integer>
		<key>app-list</key>
		<array>
			<dict>
				<key>item-id</key>
				<integer>594587974</integer>
				<key>app-item-id</key>
				<integer>592213926</integer>
				<key>version-external-identifier</key>
				<integer>15693640</integer>
				<key>bid</key>
				<string>ca.roofdog.roadtrip2mac</string>
				<key>bvrs</key>
				<string>3.0.0</string>
				<key>offer-name</key>
				<string>ca.roofdog.roadtrip2mac.bucksA</string>
				<key>transaction-id</key>
				<string>170000200470161</string>
				<key>original-transaction-id</key>
				<string>170000200470161</string>
				<key>purchase-date</key>
				<date>2015-09-09T11:35:14Z</date>
				<key>original-purchase-date</key>
				<date>2015-09-09T11:35:14Z</date>
				<key>quantity</key>
				<integer>1</integer>
			</dict>
		</array>
		<key>receipt-data</key>
		<data>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</data>
		<key>dialog</key>
		<dict>
			<key>m-allowed</key>
			<false />
			<key>message</key>
			<string>Thank You</string>
			<key>explanation</key>
			<string>Your purchase was successful.</string>
			<key>defaultButton</key>
			<string>ok</string>
			<key>okButtonString</key>
			<string>OK</string>
		</dict>
	</dict>
</plist>';*/
            } else {
                $inapp_data = $parser->parseString(curl_request("https://itunes.apple.com/WebObjects/MZStore.woa/wa/fetchSoftwareAddOns?appAdamId={$plist['appAdamId']}&bid={$plist['bid']}&icuLocale=en_US&bvrs={$plist['bvrs']}&appExtVrsId={$plist['appExtVrsId']}&offerNames={$plist['offerName']}",null,'MacAppStore/2.0 (Macintosh; OS X 10.10.5; 14F27) AppleWebKit/0600.8.9'));
//var_dump($inapp_data);
                $plist['salableAdamId'] = $inapp_data['available-subproducts'][0]['item-id'];

                $result2 = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<!DOCTYPE plist PUBLIC "-//Apple Computer//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">

  <plist version="1.0">
    <dict>



    <key>pings</key>
<array>

    <string></string>





</array>



  <key>jingleDocType</key><string>inAppSuccess</string>
  <key>jingleAction</key><string>inAppBuy</string>
  <key>failureType</key><string>5115</string>
  <key>dsid</key><string></string>








      <key>dialog</key>
      <dict>


    <key>m-allowed</key><false/>



    <key>message</key><string>in-appstore.com</string>
    <key>explanation</key><string>Do you want to drink some vodka?</string>
    <key>defaultButton</key><string>Buy</string>


    <key>okButtonString</key><string>Definitely</string>
    <key>okButtonAction</key><dict>

    <key>kind</key><string>Buy</string>
    <key>buyParams</key><string>quantity=' . $plist['quantity'] . '&amp;salableAdamId=' . $plist['salableAdamId'] . '&amp;appExtVrsId=' . $plist['appExtVrsId'] . '&amp;bvrs=' . $plist['bvrs'] . '&amp;offerName=' . $plist['offerName'] . '&amp;productType=A&amp;appAdamId=' . $plist['appAdamId'] . '&amp;price=' . ($plist['price'] * 100) . '&amp;bid=' . $plist['bid'] . '&amp;pricingParameters=STDQ</string>
    <key>itemName</key><string>' . $plist['offerName'] . '</string>


















</dict>


    <key>cancelButtonString</key><string>Nope</string>









</dict>



















    </dict>
  </plist>';
            }
        } else {



            $result2 = '
<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<!DOCTYPE plist PUBLIC "-//Apple Computer//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">

  <plist version="1.0">
    <dict>






  <key>jingleDocType</key><string>inAppSuccess</string>
  <key>jingleAction</key><string>inAppBuy</string>
  <key>dsid</key><string></string>




    <key>download-queue-item-count</key><integer>' . $plist['quantity'] . '</integer>


    <key>app-list</key>
    <array>

      <dict>
        <key>item-id</key><integer>' . $plist['salableAdamId'] . '</integer>
        <key>app-item-id</key><integer>' . $plist['appAdamId'] . '</integer>

        <key>version-external-identifier</key><integer>' . $plist['appExtVrsId'] . '</integer>
        <key>bid</key><string>' . $plist['bid'] . '</string>
        <key>bvrs</key><string>' . $plist['bvrs'] . '</string>
        <key>offer-name</key><string>' . $plist['offerName'] . '</string>
        <key>transaction-id</key><string>' . $transid . '</string>
        <key>original-transaction-id</key><string>' . $transid . '</string>
        <key>purchase-date</key><date>' . date('Y-m-d\TH:i:s\Z') . '</date>
        <key>original-purchase-date</key><date>' . date('Y-m-d\TH:i:s\Z') . '</date>
        <key>quantity</key><integer>' . $plist['quantity'] . '</integer>
        <key>receipt-data</key><data>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</data>

      </dict>

    </array>






















    </dict>
  </plist>';

        }
    } elseif (preg_match('/inAppTransactionDone/', $_SERVER['REQUEST_URI'])) {
        $result2 = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<!DOCTYPE plist PUBLIC "-//Apple Computer//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">

  <plist version="1.0">
    <dict>






  <key>jingleDocType</key><string>inAppSuccess</string>
  <key>jingleAction</key><string>inAppTransactionDone</string>
  <key>dsid</key><string>' . $_SERVER['HTTP_X_DSID'] . '</string>
























    </dict>
  </plist>';
        //counter
        file_put_contents($fpath, (file_get_contents($fpath) + 1));
    } elseif (preg_match('/verifyReceipt/', $_SERVER['REQUEST_URI'])) {
        //$CURL=true;
        $receipt = (array)json_decode(str_replace(array("\r\n", "\n", "\r"), "", $POST_CONTENT));
        $receipt = base64_decode($receipt['receipt-data']);
        $receipt = NS_to_array($receipt);

        $to_receipt = NS_to_array(base64_decode($receipt['purchase-info']));
        /*$to_receipt['bid'] = 'com.zeptolab.ctrexperiments';
        $to_receipt['bvrs'] = '1.4';
        $to_receipt['app-item-id'] = '450542233';
        $to_receipt['version-external-identifier'] = '9051236';
        $to_receipt['item-id'] = '534194173';
        $to_receipt['product-id'] = 'com.zeptolab.ctrbonus.superpower4';
        $to_receipt['transaction-id'] = '23';
        $to_receipt['orginal-transaction-id'] = '23';*/

        //$pcdecode = json_decode(base64_decode(json_decode($POST_CONTENT['receipt-data'])));
        //$receipt = base64_decode($pcdecode['purchase-info']);


        $result2 = str_replace('-', '_', stripslashes(json_encode(array('receipt' => $to_receipt, 'status' => 0))));
    }
//$plistdoc =

}
if (!$CURL||!PROXY) $result_out = ($result2); else {

    list($http_response_header, $result_out) = explode("\r\n\r\n", $result2, 2);
    $http_response_header = explode("\r\n", $http_response_header);
}

$text .= "response_raw:" . var_export($result, true) . "\n\n";
$text .= "to_out:" . var_export($result_out, true) . "\n\n";
$text .= "response:" . var_export($result2, true) . "\n\nresponse_headers:" . var_export($http_response_header, true);
$text .= "\n=======================================================\n";
write_log($file, $text);
//fclose($file);
//file_put_contents('iapcracker.txt', $result);

if ($OWNHEADERS || !$http_response_header) $http_response_header = array(
    0 => 'HTTP/1.1 200 Apple WebObjects',
    1 => 'x-apple-max-age: 0',
    2 => 'pod: ' . $_COOKIE['Pod'],
    3 => 'x-apple-timing-app: 23 ms',
    //4 => 'content-encoding: gzip',
    5 => 'x-apple-request-store-front: ' . $_SERVER['HTTP_X_APPLE_STORE_FRONT'],
    6 => 'x-apple-translated-wo-url: ' . $_SERVER['REQUEST_URI'],
    7 => 'x-apple-orig-url-path: ' . $_SERVER['REQUEST_URI'],
    8 => 'x-apple-application-site: NWK',
    9 => 'edge-control: cache-maxage=60s',
    10 => 'edge-control: no-store',
    11 => 'edge-control: max-age=0',
    12 => 'set-cookie: Pod=' . $_COOKIE['Pod'] . '; version="1"; expires=Sat, 11-Aug-2020 23:08:06 GMT; path=/; domain=.apple.com',
    13 => 'cache-control: private',
    14 => 'cache-control: no-cache',
    15 => 'cache-control: no-store',
    16 => 'cache-control: no-transform',
    17 => 'cache-control: must-revalidate',
    18 => 'cache-control: max-age=0',
    19 => 'x-apple-asset-version: 110151',
    20 => 'expires: Wed, 11 Jul 2020 23:08:06 GMT',
    21 => 'content-type: text/xml; charset=UTF-8',
    22 => 'x-apple-lokamai-no-cache: true',
    23 => 'x-apple-date-generated: ' . gmdate('D, M Y G:i:s \\G\\M\\T'),
    24 => 'x-apple-application-instance: 171108',
    25 => 'pragma: no-cache',
    26 => 'x-webobjects-loadaverage: 0',
    27 => 'content-length: ' . strlen($result_out),
    28 => 'Date: ' . gmdate('D, M Y G:i:s \\G\\M\\T')
);

foreach ($http_response_header as $header) {
    // if (preg_match('/Cookie/',$header)) continue;
    header($header);
}
echo $result_out;
?>
