<?php


namespace App\Http\Services;


use App\Utils;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils as UtilsRequest;

class SumsubKYCService
{
    private $sumsubAppToken;
    private $sumsubSecretKey;
    private $sumsubLevelName;
    const SUMSUB_BASE_URL = "https://api.sumsub.com";

    public function __construct()
    {
        $this->sumsubAppToken = env("SUMSUB_APP_TOKEN", "sbx:zMp62oolQnONo3cLFFAGQ2j6.ylE40LNdeBIRpveongoNI3zSdWoyQFbY");
        $this->sumsubSecretKey = env("SUMSUB_SECRET_KEY", "s8VmOnFPSy5NX4Oi71dWbRf1m3MJobHQ");
        $this->sumsubLevelName = env('SUMSUB_LEVEL_NAME', 'test-kyc-level');
    }

    public function getExternalUserId($userId) {
        return env("SUMSUB_PREFIX", "drx_") . $userId;
    }

    public function createApplicant($userId, $firstName, $lastName, string $lang = 'en')
    {
        $externalUserId = $this->getExternalUserId($userId);
        $requestBody = [
            'externalUserId' => $externalUserId,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'lang' => $lang
        ];
        //$url = '/resources/applicants';
        $url = '/resources/applicants?'.http_build_query(['levelName' => $this->sumsubLevelName]);
        $request = new Request('POST', self::SUMSUB_BASE_URL.$url);
        $request = $request->withHeader('Content-Type', 'application/json');
        $request = $request->withBody(UtilsRequest::streamFor(json_encode($requestBody)));

        $responseBody = $this->sendHttpRequest($request, $url)->getBody();
        return json_decode($responseBody, true);
    }

    public function checkUserStatus($userId) {
        $externalUserId = $this->getExternalUserId($userId);
        $url = "/resources/applicants/-;externalUserId={$externalUserId}/one";
        $request = new Request('GET', self::SUMSUB_BASE_URL.$url);
        $request = $request->withHeader('Content-Type', 'application/json');

        $responseBody = $this->sendHttpRequest($request, $url)->getBody();
        return json_decode($responseBody, true);
    }

    public function changeApplicantsInfo($applicantId, $data) {
        $url = "/resources/applicants/{$applicantId}/fixedInfo";
        $request = new Request('PATCH', self::SUMSUB_BASE_URL.$url);
        $request = $request->withHeader('Content-Type', 'application/json');
        $request = $request->withBody(UtilsRequest::streamFor(json_encode($data)));

        $responseBody = $this->sendHttpRequest($request, $url)->getBody();
        return json_decode($responseBody, true);
    }

    public function resetApplicantsProfile($applicantId) {
        $url = "/resources/applicants/{$applicantId}/reset";
        $request = new Request('POST', self::SUMSUB_BASE_URL.$url);
        $request = $request->withHeader('Content-Type', 'application/json');

        $responseBody = $this->sendHttpRequest($request, $url)->getBody();
        return json_decode($responseBody, true);
    }

    public function onHoldReviewApplicantsProfile($applicantId) {
        $url = "/resources/applicants/{$applicantId}/review/status/onHold";
        $request = new Request('POST', self::SUMSUB_BASE_URL.$url);
        $request = $request->withHeader('Content-Type', 'application/json');

        $responseBody = $this->sendHttpRequest($request, $url)->getBody();
        return json_decode($responseBody, true);
    }

    public function getApplicantsStatus($applicantId) {
        $url = "/resources/applicants/{$applicantId}/status";
        $request = new Request('GET', self::SUMSUB_BASE_URL.$url);
        $request = $request->withHeader('Content-Type', 'application/json');

        $responseBody = $this->sendHttpRequest($request, $url)->getBody();
        return json_decode($responseBody, true);
    }

    public function getApplicantsData($applicantId) {
        $url = "/resources/applicants/{$applicantId}/one";
        $request = new Request('GET', self::SUMSUB_BASE_URL.$url);
        $request = $request->withHeader('Content-Type', 'application/json');

        $responseBody = $this->sendHttpRequest($request, $url)->getBody();
        return json_decode($responseBody, true);
    }

    public function getWebSDKLink($userId) {
        $externalUserId = $this->getExternalUserId($userId);
        $url = "/resources/sdkIntegrations/levels/" . $this->sumsubLevelName. "/websdkLink?externalUserId={$externalUserId}";

        $request = new Request('POST', self::SUMSUB_BASE_URL.$url);
        $request = $request->withHeader('Content-Type', 'application/json');

        $responseBody = $this->sendHttpRequest($request, $url)->getBody();
        return json_decode($responseBody, true);
    }

    public function sendHttpRequest($request, $url) {
        $client = new Client();
        $ts = round(time());

        $request = $request->withHeader('X-App-Token', $this->sumsubAppToken);
        $request = $request->withHeader('X-App-Access-Sig', $this->createSignature($ts, $request->getMethod(), $url, $request->getBody()));
        $request = $request->withHeader('X-App-Access-Ts', $ts);


        return $client->send($request);
    }

    public function updateStatus($userKyc) {

    }

    private function getUrlDocImage($applicantId, $userId, $idDoc) {
        $isExist = Utils::checkFileKYCUserExists($userId, $idDoc);
        $data = null;
        if (!$isExist) {
            //get content image
            $url = "/resources/inspections/{$applicantId}/resources/".$idDoc;
            $request = new Request('GET', self::SUMSUB_BASE_URL.$url);
            $request = $request->withHeader('Content-Type', 'application/json');

            $result = $this->sendHttpRequest($request, $url);
            if ($result->getStatusCode() == 200) {
                $data = $result->getBody()->getContents();
            }
        }
        $url = Utils::saveFileKYCUser($data, $userId, $idDoc);
        return $url ? $url : null;
    }

    public function getDocumentImage($userKyc) {
        $docs = [
            'id_front' => null,
            'id_back' => null,
            'id_selfie' => null
        ];

        //get docs status

        $externalUserId = $this->getExternalUserId($userKyc->user_id);
        $applicantId = $userKyc->id_applicant;

        $url = "/resources/applicants/{$applicantId}/requiredIdDocsStatus";

        $request = new Request('GET', self::SUMSUB_BASE_URL.$url);
        $request = $request->withHeader('Content-Type', 'application/json');

        $responseBody = $this->sendHttpRequest($request, $url)->getBody();
        $result = json_decode($responseBody, true);
        $imageIdsIDENTITY = isset($result['IDENTITY']) && isset($result['IDENTITY']['imageIds']) ? $result['IDENTITY']['imageIds'] : [];
        $imageIdsSELFIE = isset($result['SELFIE']) && isset($result['SELFIE']['imageIds']) ? $result['SELFIE']['imageIds'] : [];

        $idSelfie = count($imageIdsSELFIE) > 0 ? $imageIdsSELFIE[0] : '';
        $idFront = count($imageIdsIDENTITY) > 0 ? $imageIdsIDENTITY[0] : '';
        $idBack = count($imageIdsIDENTITY) > 1 ? $imageIdsIDENTITY[1] : '';
        if ($idSelfie) {
            $docs['id_selfie'] = $this->getUrlDocImage($applicantId, $externalUserId, $idSelfie);
        }

        if ($idFront) {
            $docs['id_front'] = $this->getUrlDocImage($applicantId, $externalUserId, $idFront);
        }

        if ($idBack) {
            $docs['id_back'] = $this->getUrlDocImage($applicantId, $externalUserId, $idBack);
        }

        if ($docs['id_selfie'] == $userKyc->id_selfie) {
            unset($docs['id_selfie']);
        }
        if ($docs['id_back'] == $userKyc->id_back) {
            unset($docs['id_back']);
        }
        if ($docs['id_front'] == $userKyc->id_front) {
            unset($docs['id_front']);
        }

        return $docs;


    }

    private function createSignature($ts, $httpMethod, $url, $httpBody): string
    {
        return hash_hmac('sha256', $ts . strtoupper($httpMethod) . $url . $httpBody, $this->sumsubSecretKey);
    }

    public function getcountries()
    {
        return [
            "AFG" => "Afghanistan",
            "ALB" => "Albania",
            "DZA" => "Algeria",
            "ASM" => "American Samoa",
            "AND" => "Andorra",
            "AGO" => "Angola",
            "AIA" => "Anguilla",
            "ATG" => "Antigua and Barbuda",
            "ARG" => "Argentina",
            "ARM" => "Armenia",
            "ABW" => "Aruba",
            "AUS" => "Australia",
            "AUT" => "Austria",
            "AZE" => "Azerbaijan",
            "BHS" => "The Bahamas",
            "BHR" => "Bahrain",
            "BGD" => "Bangladesh",
            "BRB" => "Barbados",
            "BLR" => "Belarus",
            "BEL" => "Belgium",
            "BLZ" => "Belize",
            "BEN" => "Benin",
            "BMU" => "Bermuda",
            "BTN" => "Bhutan",
            "BOL" => "Bolivia",
            "BIH" => "Bosnia and Herzegovina",
            "BWA" => "Botswana",
            "BRA" => "Brazil",
            "VGB" => "British Virgin Islands",
            "BRN" => "Brunei",
            "BGR" => "Bulgaria",
            "BFA" => "Burkina Faso",
            "BDI" => "Burundi",
            "KHM" => "Cambodia",
            "CMR" => "Cameroon",
            "CAN" => "Canada",
            "CPV" => "Cape Verde",
            "CYM" => "Cayman Islands",
            "CAF" => "Central African Republic",
            "TCD" => "Chad",
            "CHL" => "Chile",
            "CHN" => "China",
            "CXR" => "Christmas Island",
            "CCK" => "Cocos (Keeling) Islands",
            "COL" => "Colombia",
            "COM" => "Comoros",
            "COG" => "Republic of the Congo",
            "COK" => "Cook Islands",
            "CRI" => "Costa Rica",
            "CIV" => "Cote d'Ivoire",
            "HRV" => "Croatia",
            "CUB" => "Cuba",
            "CYP" => "Cyprus",
            "CZE" => "Czechia",
            "DNK" => "Denmark",
            "DJI" => "Djibouti",
            "DMA" => "Dominica",
            "DOM" => "Dominican Republic",
            "ECU" => "Ecuador",
            "EGY" => "Egypt",
            "SLV" => "El Salvador",
            "GNQ" => "Equatorial Guinea",
            "ERI" => "Eritrea",
            "EST" => "Estonia",
            "ETH" => "Ethiopia",
            "FLK" => "Falkland Islands (Islas Malvinas)",
            "FRO" => "Faroe Islands",
            "FJI" => "Fiji",
            "FIN" => "Finland",
            "FRA" => "France",
            "GUF" => "French Guiana",
            "PYF" => "French Polynesia",
            "GAB" => "Gabon",
            "GMB" => "The Gambia",
            "GEO" => "Georgia",
            "DEU" => "Germany",
            "GHA" => "Ghana",
            "GIB" => "Gibraltar",
            "GRC" => "Greece",
            "GRL" => "Greenland",
            "GRD" => "Grenada",
            "GLP" => "Guadeloupe",
            "GUM" => "Guam",
            "GTM" => "Guatemala",
            "GIN" => "Guinea",
            "GNB" => "Guinea-Bissau",
            "GUY" => "Guyana",
            "HTI" => "Haiti",
            "VAT" => "Holy See (Vatican City)",
            "HND" => "Honduras",
            "HUN" => "Hungary",
            "ISL" => "Iceland",
            "IND" => "India",
            "IDN" => "Indonesia",
            "IRN" => "Iran",
            "IRQ" => "Iraq",
            "IRL" => "Ireland",
            "ISR" => "Israel",
            "ITA" => "Italy",
            "JAM" => "Jamaica",
            "JPN" => "Japan",
            "JOR" => "Jordan",
            "KAZ" => "Kazakhstan",
            "KEN" => "Kenya",
            "KIR" => "Kiribati",
            "PRK" => "North Korea",
            "KOR" => "South Korea",
            "KWT" => "Kuwait",
            "KGZ" => "Kyrgyzstan",
            "LAO" => "Laos",
            "LVA" => "Latvia",
            "LBN" => "Lebanon",
            "LSO" => "Lesotho",
            "LBR" => "Liberia",
            "LBY" => "Libya",
            "LIE" => "Liechtenstein",
            "LTU" => "Lithuania",
            "LUX" => "Luxembourg",
            "MKD" => "North Macedonia",
            "MDG" => "Madagascar",
            "MWI" => "Malawi",
            "MYS" => "Malaysia",
            "MDV" => "Maldives",
            "MLI" => "Mali",
            "MLT" => "Malta",
            "IMN" => "Isle of Man",
            "MHL" => "Marshall Islands",
            "MTQ" => "Martinique",
            "MRT" => "Mauritania",
            "MUS" => "Mauritius",
            "MYT" => "Mayotte",
            "MEX" => "Mexico",
            "FSM" => "Federated States of Micronesia",
            "MDA" => "Moldova",
            "MCO" => "Monaco",
            "MNG" => "Mongolia",
            "MSR" => "Montserrat",
            "MAR" => "Morocco",
            "MOZ" => "Mozambique",
            "MMR" => "Myanmar (Burma)",
            "NAM" => "Namibia",
            "NRU" => "Nauru",
            "NPL" => "Nepal",
            "NLD" => "Netherlands",
            "ANT" => "Netherlands Antilles",
            "NCL" => "New Caledonia",
            "NZL" => "New Zealand",
            "NIC" => "Nicaragua",
            "NER" => "Niger",
            "NGA" => "Nigeria",
            "NIU" => "Niue",
            "NFK" => "Norfolk Island",
            "MNP" => "Northern Mariana Islands",
            "NOR" => "Norway",
            "OMN" => "Oman",
            "PAK" => "Pakistan",
            "PLW" => "Palau",
            "PSE" => "Palestinian Territory",
            "PAN" => "Panama",
            "PNG" => "Papua New Guinea",
            "PRY" => "Paraguay",
            "PER" => "Peru",
            "PHL" => "Philippines",
            "PCN" => "Pitcairn Islands",
            "POL" => "Poland",
            "PRT" => "Portugal",
            "PRI" => "Puerto Rico",
            "QAT" => "Qatar",
            "REU" => "Reunion",
            "ROU" => "Romania",
            "RUS" => "Russia",
            "RWA" => "Rwanda",
            "KNA" => "Saint Kitts and Nevis",
            "LCA" => "Saint Lucia",
            "SPM" => "Saint Pierre and Miquelon",
            "VCT" => "Saint Vincent and the Grenadines",
            "SMR" => "San Marino",
            "STP" => "Sao Tome and Principe",
            "SAU" => "Saudi Arabia",
            "SEN" => "Senegal",
            "SYC" => "Seychelles",
            "SLE" => "Sierra Leone",
            "SGP" => "Singapore",
            "SVK" => "Slovakia",
            "SVN" => "Slovenia",
            "SLB" => "Solomon Islands",
            "SOM" => "Somalia",
            "ZAF" => "South Africa",
            "ESP" => "Spain",
            "LKA" => "Sri Lanka",
            "SDN" => "Sudan",
            "SUR" => "Suriname",
            "SJM" => "Svalbard",
            "SWZ" => "Eswatini",
            "SWE" => "Sweden",
            "CHE" => "Switzerland",
            "SYR" => "Syria",
            "TWN" => "Taiwan",
            "TJK" => "Tajikistan",
            "TZA" => "Tanzania",
            "THA" => "Thailand",
            "TGO" => "Togo",
            "TKL" => "Tokelau",
            "TON" => "Tonga",
            "TTO" => "Trinidad and Tobago",
            "TUN" => "Tunisia",
            "TUR" => "Turkey",
            "TKM" => "Turkmenistan",
            "TCA" => "Turks and Caicos Islands",
            "TUV" => "Tuvalu",
            "UGA" => "Uganda",
            "UKR" => "Ukraine",
            "ARE" => "United Arab Emirates",
            "GBR" => "United Kingdom",
            "USA" => "United States",
            "UMI" => "United States Minor Outlying Islands",
            "URY" => "Uruguay",
            "UZB" => "Uzbekistan",
            "VUT" => "Vanuatu",
            "VEN" => "Venezuela",
            "VNM" => "Vietnam",
            "VIR" => "Virgin Islands",
            "WLF" => "Wallis and Futuna",
            "ESH" => "Western Sahara",
            "WSM" => "Western Samoa",
            "YEM" => "Yemen",
            "COD" => "Democratic Republic of the Congo",
            "ZMB" => "Zambia",
            "ZWE" => "Zimbabwe",
            "HKG" => "Hong Kong",
            "MAC" => "Macau",
            "ATA" => "Antarctica",
            "BVT" => "Bouvet Island",
            "IOT" => "British Indian Ocean Territory",
            "ATF" => "French Southern and Antarctic Lands",
            "HMD" => "Heard Island and McDonald Islands",
            "SHN" => "Saint Helena",
            "SGS" => "South Georgia and the South Sandwich Islands",
            "GGY" => "Guernsey",
            "SRB" => "Serbia",
            "BLM" => "Saint Barthélemy",
            "MNE" => "Montenegro",
            "JEY" => "Jersey",
            "CUW" => "Curaçao",
            "MAF" => "Saint Martin",
            "SXM" => "Sint Maarten",
            "TLS" => "Timor-Leste",
            "SSD" => "South Sudan",
            "ALA" => "Åland Islands",
            "BES" => "Bonaire",
            "XKS" => "Republic of Kosovo"
        ];
    }
}