<?php
require_once __DIR__ . '/../vendor/autoload.php';

define('APPLICATION_NAME', 'Google Sheets API PHP Quickstart');
define('CREDENTIALS_PATH', __DIR__.'/../credentials/authorized_tokens.json');
define('CLIENT_SECRET_PATH', __DIR__ . '/../credentials/client_secret.json');
define('SCOPES', implode(' ', array(
  Google_Service_Sheets::SPREADSHEETS_READONLY)));

if (php_sapi_name() != 'cli') {
    throw new Exception('This application must be run on the command line.');
}

$client = getClient();
$service = new Google_Service_Sheets($client);
$spreadsheetId = '1TIF8o68Cs-hO422zLZCdVqPFw61aQElnwjNEuOIYGBo';
$range = 'meta!A1:100';
$metadata = $service->spreadsheets_values->get($spreadsheetId, $range)->getValues();

$allData = [];

$language = null;
if (isset($argv[1])) {
    $language = $argv[1];
}

foreach ($metadata as $row) {
    $sheetName = $row[0];
    $rowCount = $row[1];
    $colCount = $row[2];
    $tableName = str_replace('【Master】', '', $sheetName);
    $tableData = processOne($service, $spreadsheetId, $sheetName, $rowCount, $colCount, $language);
    $allData[$tableName] = $tableData;
}

$filename = empty($language) ? 'latest.json' : 'latest_'.$language.'.json';
$file = fopen(__DIR__.'/../storage/masterdata/'.$filename, 'w');

ksort($allData);
$jsonData = json_encode($allData, JSON_PRETTY_PRINT);
fwrite($file, $jsonData);
fclose($file);

function processOne($service, $spreadsheetId, $sheetName, $rowCount, $colCount, $language)
{
    $result = [];
    $fieldsKeyByIndex = [];
    $fieldsKeyByName = [];
    printf("Processing table %s: %s rows and %s columns\n", $sheetName, $rowCount, $colCount);
    $range = $sheetName.'!A2:'.$rowCount;
    $data = $service->spreadsheets_values->get($spreadsheetId, $range)->getValues();
    printf("Rows to process: %s\n", count($data));

    foreach ($data as $rowIndex => $row) {
        if ($rowIndex == 0) {
            for ($colIndex = 0; $colIndex < $colCount; $colIndex++) {
                if (isset($row[$colIndex])) {
                    $fieldName = $row[$colIndex];
                    $fieldsKeyByIndex[$colIndex] = $fieldName;
                    $fieldsKeyByName[$fieldName] = $colIndex;
                }
            }

            continue;
        }

        $rowData = [];
        for ($colIndex = 0; $colIndex < $colCount; $colIndex++) {
            if (isset($fieldsKeyByIndex[$colIndex])) {
                $fieldName = $fieldsKeyByIndex[$colIndex];

                if (!isset($row[$colIndex])) {
                    $rowData[$fieldName] = '';
                    continue;
                }

                if (empty($fieldName) || strpos($fieldName, '_localized_')) {
                    continue;
                }

                $fieldValue = $row[$colIndex];
                if (!empty($language)) {
                    $localizedFieldName = $fieldName.'_localized_'.$language;
                    if (isset($fieldsKeyByName[$localizedFieldName])) {
                        $localizedColIndex = $fieldsKeyByName[$localizedFieldName];
                        if (isset($row[$localizedColIndex])) {
                              $fieldValue = $row[$localizedColIndex];
                        } else {
                            printf("WARNING: There's no localized value for row: %s, field: %s\n", $rowIndex, $fieldName);
                        }
                    }
                }

                $rowData[$fieldName] = $fieldValue;
            }
        }

        array_push($result, $rowData);
    }

    print "\n";
    return $result;
}

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient()
{
    $client = new Google_Client();
    $client->setApplicationName(APPLICATION_NAME);
    $client->setScopes(SCOPES);
    $client->setAuthConfig(CLIENT_SECRET_PATH);
    $client->setAccessType('offline');

  // Load previously authorized credentials from a file.
    $credentialsPath = CREDENTIALS_PATH;
    if (file_exists($credentialsPath)) {
        $accessToken = json_decode(file_get_contents($credentialsPath), true);
    } else {
      // Request authorization from the user.
        $authUrl = $client->createAuthUrl();
        printf("Open the following link in your browser:\n%s\n", $authUrl);
        print 'Enter verification code: ';
        $authCode = trim(fgets(STDIN));

      // Exchange authorization code for an access token.
        $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

      // Store the credentials to disk.
        if (!file_exists(dirname($credentialsPath))) {
            mkdir(dirname($credentialsPath), 0700, true);
        }
        file_put_contents($credentialsPath, json_encode($accessToken));
        printf("Credentials saved to %s\n", $credentialsPath);
    }
    $client->setAccessToken($accessToken);

  // Refresh the token if it's expired.
    if ($client->isAccessTokenExpired()) {
        $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        file_put_contents($credentialsPath, json_encode($client->getAccessToken()));
    }
    return $client;
}
