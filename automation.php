<?php
date_default_timezone_set('America/Sao_Paulo');
$date = date("dmYHi");
$path = getcwd();

//aqui faz o get pegando as pedidos que tem o export status false 
$getExportStatus = simplexml_load_file("https://api.umov.me/CenterWeb/api/{apiKey}/activityHistory.xml?executionExportStatus=false&activity.alternativeIdentifier=Pedidos");
$hps_id = '';
sleep(2);

foreach($getExportStatus->entries->entry as $dados){
    $hps_id .= utf8_decode($dados["id"]) . ',';
}
$hps_id = rtrim($hps_id, ', ');
echo $hps_id;

if($hps_id != ""){

    // GERA CSV

    //faço a busca no dbview e gero um AGD com as vendas acima

    include "Database.php";
    $instanciaDB = new Database();
    $GetCriacaoTarefa = $instanciaDB->getTaskFilterExportStatusFalse($hps_id);

    $row = [];
    $Id_cabecalho = array(
        'oneRow' => "C",
    );
    $cabecalho = array(
        'header' => "command;CF_tsk_origem;serviceLocal;agent;date;hour;scheduleType;activitiesOrigin;situation;active;observation",
    );

    $arq = "/logs/tarefas/AGD" . $date . "_v2.csv";
    $fp = fopen($path . $arq, "w");

    fputcsv($fp , $Id_cabecalho);
    fputcsv($fp , $cabecalho);

    // FINALIZA INPUT CABEÇALHO CSV

    //DECLARO AS HEADERS DO POST DE EXPORTSTATUS
    $headers = [
        'Content-Type:' => 'application/x-www-form-urlencoded',
        'method' => 'POST'    
    ];

    //verifica se a variável $hps_id possui um mais valores, para não usar o foreach caso possua um só 

    if (strpos($hps_id, ',')){
        echo "<br>Possui mais de 2 valores.<br>";

        foreach ($GetCriacaoTarefa as $key) {  
            $row = array(
                'command' => "I",
                'tsk_id' => $key->tsk_id,
                'loc_integrationid' => $key->loc_integrationid,
                'agent' => "worldclean",
                'data_ini' => $key->data_ini,
                'hora' => $key->hora,
                'scheduleType' => "entrega",
                'activitiesOrigin' => "7",
                'situation' => "30",
                'active' => '1',
                'observation' => $key->observation,
            );   
            
            fputcsv($fp, $row, ";");
        }

        ######    FTP

        ini_set("default_charset", "UTF-8");
        $ftp_server = "files.umov.me";
        $ftp_username   = "master.kalykimtech";
        $ftp_password   =  "";

        $conn_id = ftp_connect($ftp_server) or die("could not connect to $ftp_server");

        // login
        if (@ftp_login($conn_id, $ftp_username, $ftp_password))
        {
          echo "Conectado a $ftp_username@$ftp_server\n";
          echo "<br>";
        }
        else
        {
          echo "Não foi possível conectar com $ftp_username\n";
        }

        $remote_file_path = "/importacao/AGD" . $date . "_v2.csv";
        $local_file = "logs/tarefas/AGD" . $date . "_v2.csv";
        ftp_pasv($conn_id, true); // habilitar o modo passivo do FTP...
        ftp_put($conn_id, $remote_file_path, $local_file, FTP_BINARY) or die;
        ftp_close($conn_id);

        ######    FTP EXIT

        //post para alterar o exportStatus = true 
        $xml_string = "";
        $result = ""; 
        foreach($getExportStatus->entries->entry as $dados){

            $hps_id = utf8_decode($dados["id"]);
            echo "<BR>Echo no hps: $hps_id. <br>";
    
            $url = "https://api.umov.me/CenterWeb/api/{apiKey}/activityHistory/$hps_id.xml";
            $ini = curl_init();
            curl_setopt($ini, CURLOPT_URL, $url);
            curl_setopt($ini, CURLOPT_POST, 1);
            curl_setopt($ini, CURLOPT_HTTPHEADER, $headers);
    
            $xml = array(
                "data" => "<activityHistory><executionExportStatus>true</executionExportStatus></activityHistory>"
            );
    
            $xml_string .= implode(",", $xml);
            curl_setopt($ini, CURLOPT_POSTFIELDS, $xml);
            curl_setopt($ini, CURLOPT_RETURNTRANSFER, true);
            $result .= curl_exec($ini);
            curl_close($ini);
    
            $file = "/logs/atualiza_ExportStatus/POST_" . $date . ".xml";
            $ponteiro = fopen($path . $file, 'w'); //cria um arquivo com o nome backup.xml
            fwrite($ponteiro, $xml_string); // salva conteúdo da variável $xml dentro do arquivo backup.xml
            $ponteiro = fclose($ponteiro); //fecha o arquivo
    
            $file = "/logs/atualiza_ExportStatus/RESULT_" . $date . ".txt";
            $ponteiro = fopen($path . $file, 'w'); //cria um arquivo com o nome backup.xml
            fwrite($ponteiro, $result); // salva conteúdo da variável $xml dentro do arquivo backup.xml
            $ponteiro = fclose($ponteiro); //fecha o arquivo
    
            echo "Finaliza a linha! <br><br><br>";
            sleep(2);
        }

    }else{
        
        echo "<br>Valor único.<br>";

        $row = array(
            'command' => "I",
            'tsk_id' => $GetCriacaoTarefa->tsk_id,
            'loc_integrationid' => $GetCriacaoTarefa->loc_integrationid,
            'agent' => "worldclean",
            'data_ini' => $GetCriacaoTarefa->data_ini,
            'hora' => $GetCriacaoTarefa->hora,
            'scheduleType' => "entrega",
            'activitiesOrigin' => "7",
            'situation' => "30",
            'active' => '1',
            'observation' => $GetCriacaoTarefa->observation,
        );
        fputcsv($fp, $row, ";");



        ######    FTP

        ini_set("default_charset", "UTF-8");
        $ftp_server = "files.umov.me";
        $ftp_username   = "master.kalykimtech";
        $ftp_password   =  ""; 

        $conn_id = ftp_connect($ftp_server) or die("could not connect to $ftp_server");

        // login
        if (@ftp_login($conn_id, $ftp_username, $ftp_password))
        {
          echo "Conectado a $ftp_username@$ftp_server\n";
          echo "<br>";
        }
        else
        {
          echo "Não foi possível conectar com $ftp_username\n";
        }

        $remote_file_path = "/importacao/AGD" . $date . "_v2.csv";
        $local_file = "logs/tarefas/AGD" . $date . "_v2.csv";
        ftp_pasv($conn_id, true); // habilitar o modo passivo do FTP...
        ftp_put($conn_id, $remote_file_path, $local_file, FTP_BINARY) or die;
        ftp_close($conn_id);

        ######    FTP EXIT



            //post para alterar o exportStatus = true 
            $dados = $getExportStatus->entries->entry;
            $hps_id = utf8_decode($dados["id"]);
            //echo "<BR>Echo no hps: $hps_id. <br>";
    
            $url = "https://api.umov.me/CenterWeb/api/{apiKey}/activityHistory/$hps_id.xml";
            $ini = curl_init();
            curl_setopt($ini, CURLOPT_URL, $url);
            curl_setopt($ini, CURLOPT_POST, 1);
            curl_setopt($ini, CURLOPT_HTTPHEADER, $headers);
    
            $xml = array(
                "data" => "<activityHistory><executionExportStatus>true</executionExportStatus></activityHistory>"
            );
    
            $xml_string = implode(",", $xml);
            curl_setopt($ini, CURLOPT_POSTFIELDS, $xml);
            curl_setopt($ini, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ini);
            curl_close($ini);
    
            $file = "/logs/atualiza_ExportStatus/POST_" . $date . ".xml";
            $ponteiro = fopen($path . $file, 'w'); //cria um arquivo com o nome backup.xml
            fwrite($ponteiro, $xml_string); // salva conteúdo da variável $xml dentro do arquivo backup.xml
            $ponteiro = fclose($ponteiro); //fecha o arquivo
    
            $file = "/logs/atualiza_ExportStatus/RESULT_" . $date . ".txt";
            $ponteiro = fopen($path . $file, 'w'); //cria um arquivo com o nome backup.xml
            fwrite($ponteiro, $result); // salva conteúdo da variável $xml dentro do arquivo backup.xml
            $ponteiro = fclose($ponteiro); //fecha o arquivo
    
            echo "Finaliza a linha! <br><br><br>";
    }  

    

}else{
    echo "<h2>Não existem novas vendas</h2>";
}


