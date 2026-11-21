<?php
$db = new PDO('mysql:host=localhost', 'root', null);

function getOSInformation()
 {
     if (false == function_exists("shell_exec") || false == is_readable("/etc/os-release")) {
         return null;
     }

      $os         = shell_exec('cat /etc/os-release');
     $listIds    = preg_match_all('/.*=/', $os, $matchListIds);
     $listIds    = $matchListIds[0];

      $listVal    = preg_match_all('/=.*/', $os, $matchListVal);
     $listVal    = $matchListVal[0];

      array_walk($listIds, function(&$v, $k){
         $v = strtolower(str_replace('=', '', $v));
     });

      array_walk($listVal, function(&$v, $k){
         $v = preg_replace('/=|"/', '', $v);
     });

      return array_combine($listIds, $listVal);
 }
$osInfo = getOSInformation();

/* ------------------------------------------------------------------
 * ODBC / Actian Zen (PervasiveSQL)
 * ------------------------------------------------------------------ */
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$zenRoot = getenv('ACTIANZEN_ROOT') ?: '/usr/local/actianzen';

// Version des Zen-Clients (aus der Installationsdatei des Archivs)
function getZenClientVersion($zenRoot)
{
    $cfg = "$zenRoot/etc/postinstall.cfg";
    if (!is_readable($cfg)) return null;
    $ini = @parse_ini_file($cfg, false, INI_SCANNER_RAW);
    if (!$ini || empty($ini['ProductVer'])) return null;
    return $ini['ProductVer'] . (isset($ini['InstallBuild']) ? ' (Build ' . $ini['InstallBuild'] . ')' : '');
}

// Version des unixODBC-Treibermanagers
function getUnixOdbcVersion()
{
    if (!function_exists('shell_exec')) return null;
    $out = trim((string)@shell_exec('odbcinst --version 2>/dev/null'));
    return $out !== '' ? $out : null;
}

// Eingerichtete DSNs aus odbc.ini
function getOdbcDsns($file = '/etc/odbc.ini')
{
    if (!is_readable($file)) return [];
    $ini = @parse_ini_file($file, true, INI_SCANNER_RAW);
    if (!$ini) return [];
    unset($ini['ODBC Data Sources'], $ini['ODBC']);
    return $ini;
}

// Verbindungstest zum Standard-DSN
function testZenConnection($dsn)
{
    $result = ['ok' => false, 'ms' => null, 'tables' => null, 'error' => null];
    if (!function_exists('odbc_connect')) {
        $result['error'] = 'PHP-ODBC-Erweiterung nicht geladen';
        return $result;
    }
    $start = microtime(true);
    $conn  = @odbc_connect($dsn, '', '');
    $result['ms'] = round((microtime(true) - $start) * 1000);
    if (!$conn) {
        $result['error'] = odbc_errormsg() ?: 'Verbindung fehlgeschlagen';
        return $result;
    }
    $result['ok'] = true;
    $count = 0;
    $res = @odbc_tables($conn);
    if ($res) {
        while ($row = odbc_fetch_array($res)) {
            if (($row['TABLE_TYPE'] ?? '') === 'TABLE') $count++;
        }
        $result['tables'] = $count;
    }
    odbc_close($conn);
    return $result;
}

$odbcLoaded  = extension_loaded('odbc');
$zenVersion  = getZenClientVersion($zenRoot);
$unixOdbc    = getUnixOdbcVersion();
$dsns        = getOdbcDsns();
$zenDsn      = getenv('ZEN_DSN') ?: 'ZENDB';
$zenHost     = getenv('ZEN_HOST') ?: '';
$zenDb       = getenv('ZEN_DB') ?: '';
$zenPort     = getenv('ZEN_PORT') ?: '1583';
$runTest     = isset($_GET['zentest']);   // Test nur auf Knopfdruck
$zenTest     = ($runTest && $odbcLoaded && isset($dsns[$zenDsn])) ? testZenConnection($zenDsn) : null;
?>
<!doctype html>
<html lang=en>
<head>
    <meta charset=utf-8>
    <title>Hello World from Docker-LAMP</title>

    <style>
        @import 'https://fonts.googleapis.com/css?family=Montserrat|Raleway|Source+Code+Pro';

        body { font-family: 'Raleway', sans-serif; }
        h2 { font-family: 'Montserrat', sans-serif; }
        h3 { font-family: 'Montserrat', sans-serif; margin-top: 32px; }
        pre {
            font-family: 'Source Code Pro', monospace;

            padding: 16px;
            overflow: auto;
            font-size: 85%;
            line-height: 1.45;
            background-color: #f7f7f7;
            border-radius: 3px;

            word-wrap: normal;
        }
        .ok   { color: #1a7f37; font-weight: bold; }
        .fail { color: #cf222e; font-weight: bold; }

        .container {
            max-width: 1024px;
            width: 100%;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <img src="https://cdn.rawgit.com/mattrayner/docker-lamp/831976c022782e592b7e2758464b2a9efe3da042/docs/logo.svg" alt="Docker LAMP logo" />
            <h2>Welcome to <a href="https://github.com/mattrayner/docker-lamp" target="_blank">Docker-Lamp</a> a.k.a mattrayner/lamp</h2>
        </header>
        <article>
            <p>
                For documentation, <a href="https://github.com/mattrayner/docker-lamp" target="_blank">click here</a>.
            </p>
        </article>
        <section>
            <pre>
OS: <?php echo $osInfo['pretty_name']; ?><br/>
Apache: <?php echo apache_get_version(); ?><br/>
MySQL Version: <?php echo $db->getAttribute( PDO::ATTR_SERVER_VERSION ); ?><br/>
PHP Version: <?php echo phpversion(); ?><br/>
phpMyAdmin Version: <?php echo getenv('PHPMYADMIN_VERSION'); ?><br/>
<?php
$iniTz      = ini_get('date.timezone') ?: 'nicht gesetzt';
$currentTz  = date_default_timezone_get();      // effektive Zeitzone
$now        = new DateTime('now', new DateTimeZone($currentTz));

echo "<p>Serverzeit: " . $now->format('d.m.Y H:i:s') . "</p>";
echo "<p>PHP-Konfig-Zeitzone (php.ini): {$iniTz}</p>";
echo "<p>Aktive Zeitzone laut PHP: {$currentTz}</p>";
?>
            </pre>
        </section>

        <section>
            <h3>ODBC / Actian Zen (PervasiveSQL)</h3>
            <pre>
PHP-ODBC-Erweiterung: <?php echo $odbcLoaded ? '<span class="ok">geladen</span> (' . h(phpversion('odbc')) . ')' : '<span class="fail">nicht geladen</span>'; ?><br/>
unixODBC: <?php echo h($unixOdbc ?: 'unbekannt'); ?><br/>
Zen-Client: <?php echo h($zenVersion ?: 'nicht gefunden'); ?><br/>
Zen-Verzeichnis: <?php echo h($zenRoot); ?><br/>
<br/>
Konfiguration (Container-Variablen):<br/>
  ZEN_DSN:  <?php echo h($zenDsn); ?><br/>
  ZEN_HOST: <?php echo h($zenHost ?: '-'); ?><br/>
  ZEN_DB:   <?php echo h($zenDb ?: '-'); ?><br/>
  ZEN_PORT: <?php echo h($zenPort); ?><br/>
<br/>
Eingerichtete DSNs (/etc/odbc.ini):<br/>
<?php
if (!$dsns) {
    echo "  keine<br/>";
} else {
    foreach ($dsns as $name => $opts) {
        echo '  <b>' . h($name) . '</b><br/>';
        foreach ($opts as $key => $val) {
            echo '    ' . h($key) . ' = ' . h($val) . '<br/>';
        }
    }
}
?>
<br/>
Verbindungstest (<?php echo h($zenDsn); ?>): <?php
if (!$runTest) {
    echo 'noch nicht ausgeführt';
} elseif ($zenTest === null) {
    echo '<span class="fail">nicht möglich</span> (DSN nicht eingerichtet oder ODBC nicht geladen)';
} elseif ($zenTest['ok']) {
    echo '<span class="ok">OK</span> in ' . h($zenTest['ms']) . ' ms';
    if ($zenTest['tables'] !== null) echo ', ' . h($zenTest['tables']) . ' Tabellen';
} else {
    echo '<span class="fail">Fehler</span> nach ' . h($zenTest['ms']) . ' ms: ' . h($zenTest['error']);
}
?><br/>
            </pre>
            <form method="get" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').textContent='Teste Verbindung …';">
                <input type="hidden" name="zentest" value="1">
                <button type="submit">Verbindung zu <?php echo h($zenDsn); ?> testen</button>
                <?php if ($runTest): ?><a href="?" style="margin-left:12px">Ergebnis ausblenden</a><?php endif; ?>
            </form>
        </section>
    </div>
</body>
</html>
