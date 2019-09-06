<?php
// *************************************************************************
// *                                                                       *
// * VOXSTREAM - Radio Automation System    							   *
// * Copyright (c) CDOWEBCAST.COM. All Rights Reserved,                    *
// * Version: 7.7.1                                                        *
// * Build Date: 03 Apr 2019                                               *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * Email: contato@cdowebcast.com                                         *
// * Website: http://www.cdowebcast.com                                    *
// *                                                                       *
// *************************************************************************
// *                                                                       *
// * This software is furnished under a license and may be used and copied *
// * only  in  accordance  with  the  terms  of such  license and with the *
// * inclusion of the above copyright notice.  This software  or any other *
// * copies thereof may not be provided or otherwise made available to any *
// * other person.  No title to and  ownership of the  software is  hereby *
// * transferred.                                                          *
// *                                                                       *
// * You may not reverse  engineer, decompile, defeat  license  encryption *
// * mechanisms, or  disassemble this software product or software product *
// * license. VOXSTREAM Automation may terminate this license if you don't *
// * comply with any of the terms and conditions set forth in our end user *
// * license agreement (EULA).  In such event,  licensee  agrees to return *
// * licensor  or destroy  all copies of software  upon termination of the *
// * license.                                                              *
// *                                                                       *
// * Please see the EULA file for the full End User License Agreement.     *
// *                                                                       *
// *************************************************************************
?>

<?php
if (!defined("WHMCS"))
    exit("This file cannot be accessed directly.");

if (strnatcmp(phpversion(), '5.6.0') < 0)
    exit("Modules requires PHP version 5.6++<br><br>Please upgrade PHP");

use WHMCS\Database\Capsule;

/**
 * @return array
 */
function voxradio_ConfigOptions()
{

    $ConfigOptions = [
        'Ouvintes' => [
            'Type' => 'text',
            'Size' => '10',
            'Description' => '<br>(Máximo de ouvintes simultâneos. 999999 para Ilimitado)'
        ],
        'Bitrate' => [
            'Type' => 'dropdown',
            'Options' => '8,12,16,20,24,32,40,48,56,64,72,80,88,96,112,128,144,160,176,192,208,224,240,256,272,288,304,320',
            'Description' => '<br>(Qualidade de transmissão. Padrão AAC+ 64-kbps / MP3 128-kbps)'
        ],
        'Espaco AutoDJ' => [
            'Type' => 'text',
            'Size' => '10',
            'Description' => '<br>(Espaço para AutoDJ em MB. 1000 = 1GB, 0 desativa)'
        ],
        'AAC+ RTMP' => [
            'Type' => 'dropdown',
            'Options' => 'sim,nao',
            'Description' => '<br>(Ativa AAC+ sem plugin. Obrigatório usar Encoder AAC)'
        ],
        'Idioma' => [
            'Type' => 'dropdown',
            'Options' => 'pt-br,en-us,es',
            'Description' => '<br>(Idioma Painel - Português/English/Espanol)'
        ],
        'App Android' => [
            'Type' => 'dropdown',
            'Options' => 'sim,nao',
            'Description' => '<br>(Ativa a geração do App Android)'
        ],
        'Encoder MP3' => [
            'Type' => 'yesno',
            'Description' => '(Ativa o tipo MP3. Formato de áudio padrão.)'
        ],
        'Encoder AAC' => [
            'Type' => 'yesno',
            'Description' => '(Ativa o tipo AAC+. Obrigatório usar AAC+ RTMP)'
        ]
    ];
    return $ConfigOptions;
}

/**
 * @param $params
 * @return string
 */
function voxradio_ClientArea($params) {
    global $_LANG;
    $domain = ($params['serverhostname']) ? $params['serverhostname'] : $params['serverip'];
    $code = "<form id=loginForm action=\"http://$domain/login-autentica\" method=\"post\" target=\"_blank\"><input type=\"hidden\" name=\"porta\" value=" . $params['username'] . "><input type=\"hidden\" name=\"current-password\" value=" . $params['password'] . "><input class=\"btn btn-sm btn-default\" type=\"submit\" value=\"Login to VOX\"></form>";
    return $code;
}

/**
 * @param $params
 * @return string
 */
function voxradio_AdminLink($params) {
    global $_LANG;
    $domain = ($params['serverhostname']) ? $params['serverhostname'] : $params['serverip'];
    $code = "<form id=loginForm action=\"http://$domain/admin/login-autentica\" method=\"post\" target=\"_blank\"><input type=\"hidden\" name=\"email\" value=" . $params['serverusername'] . "><input type=\"hidden\" name=\"senha\" value=" . $params['serverpassword'] . "><input class=\"btn btn-sm btn-default\" type=submit value=\"Login to VOX\"></form>";
    return $code;
}

/**
 * @param $params
 * @return mixed|string
 */
function voxradio_CreateAccount($params)
{
    $pdo = Capsule::connection()->getPdo();
    $pdo->beginTransaction();
    try {
        $sqlcustomfields = $pdo->query('SELECT	`id` FROM `tblcustomfields` WHERE `fieldname`=\'Porta\' AND `relid`=' . $params['packageid']);
        $rowcustomfields = $sqlcustomfields->fetchColumn() + 0;
        if ($rowcustomfields == 0) {
            return ['status' => 'error', 'description' => 'O campo adicional <b>Porta</b> não existe.<br>Crie um campo adicional com o nome <b>Porta</b> em Produtos/Serviços'];
        }

        $sqlcustomfieldsvalues = $pdo->query('SELECT `value` FROM `tblcustomfieldsvalues` WHERE `fieldid`=' . $rowcustomfields . ' AND `relid`=' . $params['accountid']);
        if (($sqlcustomfieldsvalues->fetchColumn() + 0) > 0) {
            return ['status' => 'error', 'description' => 'Este streaming já esta criado.'];
        }

        $sqlcustomfieldsvalues = $pdo->prepare('INSERT INTO `tblcustomfieldsvalues` (`fieldid`, `relid`, `value`) VALUES (' . $rowcustomfields . ', ' . $params['accountid'] . ', \'\');');
        $sqlcustomfieldsvalues->execute();

        $sqlhostingconfigoptions = $pdo->query('SELECT * FROM `tblhostingconfigoptions` WHERE `relid`=\'' . $params['accountid'] . '\'');
        while ($rowhostingconfigoptions = $sqlhostingconfigoptions->fetch()) {

            $optionid = $rowhostingconfigoptions['optionid'];
            $configid = $rowhostingconfigoptions['configid'];

            $sqlproductconfigoptions = $pdo->query('SELECT * FROM tblproductconfigoptions WHERE id=\'' . $configid . '\'');
            $rowproductconfigoptions = $sqlproductconfigoptions->fetch();
            $optionname = $rowproductconfigoptions['optionname'];

            $sqlproductconfigoptionssub = $pdo->query('SELECT * FROM tblproductconfigoptionssub WHERE id=\'' . $optionid . '\'');
            $rowproductconfigoptionssub = $sqlproductconfigoptionssub->fetch();
            $optionvalue = $rowproductconfigoptionssub['optionname'];

            $optionvalue = trim($optionvalue);

            switch ($optionname) {
                case "Ouvintes":
                    $params['configoption1'] = $optionvalue;
                    break;
                case 'Bitrate':
                    $params['configoption2'] = $optionvalue;
                    break;
                case 'Espaço AutoDJ':
                    $params['configoption3'] = $optionvalue;
                    break;
                case 'AAC+ RTMP':
                    $params['configoption4'] = $optionvalue;
                    break;
                case 'Idioma':
                    $params['configoption5'] = $optionvalue;
                    break;
                case 'App Android':
                    $params['configoption6'] = $optionvalue;
                    break;
                case 'Encoder MP3':
                    $params['configoption7'] = $optionvalue;
                    break;
                case 'Encoder AAC':
                    $params['configoption8'] = $optionvalue;
                    break;
            }
        }

        $api['acao'] = 'cadastrar';
        $api['ouvintes'] = $params['configoption1'];
        $api['bitrate'] = $params['configoption2'];
        $api['espaco'] = $params['configoption3'];
        $api['senha'] = substr(md5("acegikmoqsuxywz" . time()), 0, 12);
        $api['aacplus'] = $params['configoption4'];
        $api['idioma'] = $params['configoption5'];
        $api['app_android'] = $params['configoption6'];
        $api['encoder_mp3'] = $params['configoption7'];
        $api['encoder_aacplus'] = $params['configoption8'];

        $response = voxradio_API($params, $api);
        if ($response['command'] == 'success') {
            list ($ip, $porta) = explode(':', $response['returned']);

            $sqlhosting = $pdo->prepare('UPDATE tblhosting SET	username=\'' . $porta . '\', password=\'' . encrypt($api['senha']) . '\', domain=\'' . $ip . ':' . $porta . '\', dedicatedip=\'' . $ip . '\' WHERE id=\'' . $params['accountid'] . '\'');
            $sqlhosting->execute();

            $sqlcustomfields = $pdo->query('SELECT id FROM tblcustomfields WHERE fieldname=\'Porta\' AND relid=' . $params['packageid']);
            $rowcustomfields = $sqlcustomfields->fetch();

            $sqlcustomfieldsvalues = $pdo->prepare('UPDATE tblcustomfieldsvalues SET value=\'' . $porta . '\' WHERE fieldid=\'' . $rowcustomfields['id'] . '\' AND relid=\'' . $params['accountid'] . '\'');
            $sqlcustomfieldsvalues->execute();

            $pdo->commit();
            return 'success';

        } else {

            $pdo->rollBack();
            return $response['error'];

        }

    } catch (\Exception $e) {

        logModuleCall('voxradio', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());

        $pdo->rollBack();
        return ['status' => 'error', 'description' => 'Erro grave ao criar streaming (createaccount): ' . $e->getMessage()];

    }

}

/**
 * @param $params
 * @return mixed|string
 */
function voxradio_TerminateAccount($params)
{
    $pdo = Capsule::connection()->getPdo();
    $pdo->beginTransaction();
    try {

        $sqlcustomfieldsvalues = $pdo->query('SELECT tblcustomfieldsvalues.value FROM tblcustomfieldsvalues, tblcustomfields WHERE tblcustomfields.fieldname=\'Porta\' AND tblcustomfieldsvalues.fieldid = tblcustomfields.id AND tblcustomfieldsvalues.relid=' . $params['accountid']);
        $rowcustomfieldsvalues = $sqlcustomfieldsvalues->fetchColumn() + 0;
        if ($rowcustomfieldsvalues == 0) {
            return ['status' => 'error', 'description' => 'Este streaming não esta criado.'];
        }

        $api['acao'] = 'remover';
        $api['porta'] = $rowcustomfieldsvalues;

        $response = voxradio_API($params, $api);

        if ($response['command'] == 'success') {
            $sqlcustomfields = $pdo->query('SELECT id FROM tblcustomfields WHERE fieldname=\'Porta\' AND relid=' . $params['packageid']);
            $rowcustomfields = $sqlcustomfields->fetch();

            $sqlcustomfieldsvalues = $pdo->prepare('DELETE FROM tblcustomfieldsvalues WHERE fieldid=\'' . $rowcustomfields['id'] . '\' AND relid=\'' . $params['accountid'] . '\'');
            $sqlcustomfieldsvalues->execute();

            $sqltblhosting = $pdo->prepare('UPDATE tblhosting SET username=\'\', password=\'\', domain=\'\', dedicatedip=\'\' WHERE id=\'' . $params['accountid'] . '\'');
            $sqltblhosting->execute();

            $pdo->commit();
            return 'success';

        } else {

            $pdo->rollBack();
            return $response['error'];

        }

    } catch (\Exception $e) {

        logModuleCall('voxradio', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());

        $pdo->rollBack();
        return ['status' => 'error', 'description' => 'Erro grave ao encerrar streaming (terminateaccount): ' . $e->getMessage()];
    }

}

/**
 * @param $params
 * @return mixed|string
 */
function voxradio_SuspendAccount($params)
{
    $pdo = Capsule::connection()->getPdo();

    $sqlcustomfieldsvalues = $pdo->query('SELECT tblcustomfieldsvalues.value FROM tblcustomfieldsvalues, tblcustomfields WHERE tblcustomfields.fieldname=\'Porta\' AND tblcustomfieldsvalues.fieldid = tblcustomfields.id AND tblcustomfieldsvalues.relid=' . $params['accountid']);
    $rowcustomfieldsvalues = $sqlcustomfieldsvalues->fetchColumn() + 0;
    if ($rowcustomfieldsvalues == 0) {
        return ['status' => 'error', 'description' => 'Este streaming não esta criado.'];
    }

    $api['acao'] = 'bloquear';
    $api['porta'] = $rowcustomfieldsvalues;

    $response = voxradio_API($params, $api);

    if ($response['command'] == 'success') {
        return 'success';
    } else {
        return $response['error'];
    }

}

/**
 * @param $params
 * @return mixed|string
 */
function voxradio_UnsuspendAccount($params)
{
    $pdo = Capsule::connection()->getPdo();

    $sqlcustomfieldsvalues = $pdo->query('SELECT tblcustomfieldsvalues.value FROM tblcustomfieldsvalues, tblcustomfields WHERE tblcustomfields.fieldname=\'Porta\' AND tblcustomfieldsvalues.fieldid = tblcustomfields.id AND tblcustomfieldsvalues.relid=' . $params['accountid']);
    $rowcustomfieldsvalues = $sqlcustomfieldsvalues->fetchColumn() + 0;
    if ($rowcustomfieldsvalues == 0) {
        return ['status' => 'error', 'description' => 'Este streaming não esta criado.'];
    }

    $api['acao'] = 'desbloquear';
    $api['porta'] = $rowcustomfieldsvalues;

    $response = voxradio_API($params, $api);

    if ($response['command'] == 'success') {
        return 'success';
    } else {
        return $response['error'];
    }

}

/**
 * @param $serverhostname
 * @param $serveraccesshash
 * @param $api
 * @return array
 */
function voxradio_API($params, $api)
{

    $requisicao = '';
    foreach ($api as $option => $setting) {
        if (is_array($setting)) {
            $setting = serialize($setting);
        }
        $requisicao .= $setting . "/";
    }

    $domain = ($params['serverhostname']) ? $params['serverhostname'] : $params['serverip'];
    $url_requisicao = "http://" . $domain . "/admin/api/" . $params['serveraccesshash'] . "/" . $requisicao . "";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url_requisicao);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 200);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_USERAGENT, 'VOXSTREAM WHMCS 7.7.1 (' . $_SERVER['HTTP_HOST'] . ')');
    $resultado = curl_exec($ch);
    curl_close($ch);

    if (curl_errno( $ch )) {
        $resultado = curl_errno( $ch ) . " - " . curl_error( $ch );
    }

    logModuleCall( "voxradio", $api['acao'], $params, $url_requisicao, $resultado );

    if ($resultado === false) {
        return array('command' => 'failed', 'error' => 'Não foi possível conectar no painel da rádio. Debug: ' . $url_requisicao . '');
    } else {
        if ($resultado) list ($status, $porta, $msg) = explode('|', $resultado);
        if ($status == '0') {
            return array('command' => 'failed', 'error' => '' . $msg . '');
        }
        return array('command' => 'success', 'returned' => '' . $porta . '');
    }
}
