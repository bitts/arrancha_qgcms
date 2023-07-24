<?php

/**
Arquivo para teste do webservice / arquivo utilizado para migrar script bash de agendamento de arranchamentos para arranchamentos via webservice ou terminal
Objetivo: 
    - tratar melhor erros e mensagens para o usuario
    - estruturar codigo feito a facao
    - trabalhar com diferentes maneiras de realizar requisicoes a servicos e servidores remotos utilizando diferentes bibliotecas nativas do PHP
*/
session_start();

ini_set('memory_limit', '256M');
ini_set('max_execution_time', 3600);

DEFINE("DEBUG",true);

if(DEBUG){
    ini_set('display_errors',1);
    ini_set('display_startup_erros',1);
    error_reporting(E_ERROR | E_ERROR | E_NOTICE);
}

function login($_user, $_pswd){
    $obj = new stdClass();
    try{
        if(!isset($_user) && empty($_user))$obj->error[] = "[ERROR] Necessário informar USUARIO do Sila";
        if(!isset($_pswd) && empty($_pswd))$obj->error[] = "[ERROR] Necessário informar SENHA de acesso do usuário do Sila";

        $obj->cookie_file = $cookie_file = "/tmp/cookies_sila_{$_user}.txt";
        if(!file_exists($cookie_file)) {

            $fh = fopen($cookie_file, "w");
            if($fh){
                fwrite($fh, "");
                fclose($fh);
            }else $obj->error[] = "[ERROR] Não foi possível criar arquivo de cookies.";
            if(function_exists('exec'))exec("chmod 777 {$cookie_file}");
            else $obj->error[] = "[ERROR] Não é possível verificar permissão de gravação em disco.";

            //$obj->error[] = "[MESSAGE] Não é possível alterar permissões de leitura/gravação do arquivo {$cookie_file} necessário para realizar o login no sistema.";
        }else $obj->info[] = "[MESSAGE] Arquivo {$cookie_file} não encontrado.";

        if(function_exists('is_writable')){
            $obj->info[] = "[MESSAGE] O arquivo de cookies locais {$cookie_file} <b>". (is_writable($cookie_file)?"SIM":"NÃO") . "</b> possui permissão de escrita.\n";
        }

        if( (!isset($obj->error) || empty($obj->error)) && (0 == filesize( $cookie_file )) && !empty($_user) && !empty($_pswd)){
            $obj->info[] = "[MESSAGE] O arquivo {$cookie_file} encontra-se fazio e um novo login será realizado.\n";
            try{
                $postfields = array(
                    'fcpf' => $_user,
                    'fsnh' => $_pswd,
                    'acao' => 'login'
                );

                $pagina = "http://sila.3rm.eb.mil.br/login.php";

                if(function_exists('file_get_contents')){
                    $arrContextOptions = array(
                        "ssl" => array(
                            "verify_peer" => false,
                            "verify_peer_name" => false
                        ),
                        "http" => array(
                            "ignore_errors" => true,
                            "method" => "POST",
                            "content" =>  json_encode($postfields)
                        )
                    );
                    $context = stream_context_create($arrContextOptions);
                    $result = file_get_contents($pagina, false, $context);

                    $cookies = array();
                    foreach ($result as $hdr) {
                        if (preg_match('/^Set-Cookie:\s*([^;]+)/', $hdr, $matches)) {
                            parse_str($matches[1], $tmp);
                            $cookies[] = $tmp;
                        }
                    }
                    if(!empty($cookies)){
                        $obj->cookie_content = implode('; ', $cookies);

                        if(false !== file_put_contents($cookie_file, $cookies))$obj->error[] = "[ERROR] Não foi possível criar o arquivo de cookies.";
                        else $obj->success[] = "[SUCCESS] Arquivo de Cookies criado com sucesso";
                    }else $obj->error[] = "[ERROR] Não foi possível criar o arquivo de cookies.";

                    if($result){
                        $status_line = $http_response_header[0];
                        preg_match('{HTTP\/\S*\s(\d{3})}', $status_line, $match);

                        $obj->methodo = 'file_get_contents';
                        $obj->result = $result;
                        $obj->statuscode = $match[1];
                        $obj->success[] = "[SUCCESS] Usuário ja logado";
                    }
                }else $obj->info[] = "[MESSAGE] Não é possivel utilizar FILE_GET_CONTENTS para enviar dados para o servidor.";

                if(!isset($obj->result) && function_exists('fopen')){
                    try{
                        $arrContextOptions = array(
                            "ssl" => array(
                                "verify_peer" => false,
                                "verify_peer_name" => false,
                            ),
                            "http" => array(
                                "method" => "POST",
                                "content" =>  json_encode($postfields)
                            )
                        );

                        $context = stream_context_create($arrContextOptions);
                        $fp = fopen($pagina, 'rb', false, $context);
                        if ($fp){
                            $result = stream_get_contents($fp);
                            if ($result !== false){
                                $stream = stream_get_meta_data($fp);
                                preg_match('{HTTP\/\S*\s(\d{3})}', $stream['wrapper_data'][0], $match);
                                $obj->result = $result;
                                $obj->statuscode = $match[1];
                                $obj->methodo = 'fopen';
                                $obj->success[] = "[SUCCESS] Login realizado.";

                                $cookies = array();
                                foreach ($result as $hdr) {
                                    if (preg_match('/^Set-Cookie:\s*([^;]+)/', $hdr, $matches)) {
                                        parse_str($matches[1], $tmp);
                                        $cookies[] = $tmp;
                                    }
                                }
                                if(!empty($cookies)){
                                    $obj->cookie_content = implode('; ', $cookies);
                                    $fp = fopen($cookie_file, "a");
                                    $escreve = fwrite($fp, $cookies);
                                    if($escreve)$obj->error[] = "[ERROR] Não foi possível criar o arquivo de cookies.";
                                    else $obj->success[] = "[SUCCESS] Arquivo de Cookies criado com sucesso";
                                    fclose($fp);
                                }else $obj->error[] = "[ERROR] Não foi possível criar o arquivo de cookies.";
                            }
                        }else fclose($fp);
                    } catch (Exception $e) {
                        $obj->error[] = $e->getMessage();
                    }
                }else $obj->info[] = "[MESSAGE] Não é possivel utilizar FOPEN para enviar dados para o servidor.";

                if(!isset($obj->result) && function_exists('curl_init')){
                    $curl = curl_init();
                    $postfields = http_build_query($postfields);
                    try{
                        curl_setopt($curl, CURLOPT_COOKIEJAR, $cookie_file );
                        curl_setopt($curl, CURLOPT_URL, $pagina);
                        curl_setopt($curl, CURLOPT_HEADER, true);
                        curl_setopt($curl, CURLOPT_POST, 1);
                        curl_setopt($curl, CURLOPT_POSTFIELDS, $postfields );
                        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
                        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

                        $result = curl_exec($curl);
                        if(!$result){
                            $obj->error[] = "[ERROR] Falha de conexão com o Servidor.";
                        }else if (curl_error($curl)){
                            $obj->error[] = "[ERROR] Erro do CURL" . curl_error($curl);
                        }else{
                            $obj->result = $result;
                            $obj->statuscode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                            $obj->methodo = 'curl';
                            $obj->success[] = "[SUCCESS] Usuário logado com sucesso.";

                            $cookies = array();
                            foreach ($result as $hdr) {
                                if (preg_match('/^Set-Cookie:\s*([^;]+)/', $hdr, $matches)) {
                                    parse_str($matches[1], $tmp);
                                    $cookies[] = $tmp;
                                }
                            }
                            if(!empty($cookies)){
                                $obj->cookie_content = implode('; ', $cookies);
                            }else {
                                $obj->error[] = "[ERROR] Não foi possível criar o arquivo de cookies.";
                            }
                        }

                        $resposta_http = curl_getinfo($result, CURLINFO_HTTP_CODE);
                        if($resposta_http < 200 && $resposta_http > 299){
                            $obj->error[] = "[ERROR] Erro ao carregar a página [Código: {$resposta_http}].";
                        }else $obj->success[] = "[SUCCESS] Página carregada [Código: {$resposta_http}].";
                    } catch (Exception $e) {
                        $obj->error[] = $e->getMessage();
                    } finally {
                        curl_close($curl);
                    }
                }else $obj->info[] = "[MESSAGE] Não é possivel utilizar CURL para enviar dados para o servidor.";

            }catch(Exception $e) {
                $obj->error[] = $e->getMessage();
            }
        }

    }catch(Exception $e) {
        $obj->error[] = $e->getMessage();
    }
    return $obj;
}

function arranchar($_data, $_tipo){
    $obj = new stdClass();
    try{
        if(isset($_tipo) && !empty($_tipo))$obj->error[] = "[ERROR] Tipo de alimentação não informada - valor default ALMOÇO";
        if(isset($_data) && !empty($_data)){
            $vlr = new DateTime(date('d-m-Y'));
            $vlr->modify('+1 day');
            $obj->error[] = "[ERROR] Data de arranchamento não informada - valor default AMANHA: {$vlr->format('d/m/Y')}";
        }

        $obj->info[] = "[SUCCESS] Sistema já logado, enviando requisição de arranchamento...";

        $data_arrancha = $data = null; 
        $arrancha = array();
        $pagina = 'http://sila.3rm.eb.mil.br/aprov/reverte2.php';

        if(!isset($_data) || empty($_data) ){
            $data = new DateTime(date('d-m-Y'));
            $data->modify('+1 day');
            $data_arrancha = $data->format('d-m-Y');
            $obj->info[] = "[MESSAGE] Data não informada. Definido por default a data do dia seguinte {$data_arrancha}.";
        }else{
            $obj->info[] = "[MESSAGE] Data informada por parametro é {$_data}.";
            if(preg_match("/^(0[1-9]|[1-2][0-9]|3[0-1])-(0[1-9]|1[0-2])-[0-9]{2}$/",$_data)){
                $data = new DateTime(date($_data));
                $data_arrancha = $data->format('d-m-Y');
                $obj->info[] = "[MESSAGE] Data formatada para {$data_arrancha} (padrão de data do SILA).";
            }else {
                $data = new DateTime(date('d-m-Y'));
                $data_arrancha = $data->format('d-m-Y');
                $obj->error[] = "[ERROR] Data informada no formato errado, utilize no padrão dd-mm-yy (Ex.: 13-06-21). Definido por default a data {$data_arrancha}.";
            }
        }

        if((isset($data_arrancha) || empty($dbg->result)) && !empty($data_arrancha)){
            $obj->info[] = "[MESSAGE] Tipo de refeição informada por parametro é {$_tipo}.";
            if(!isset($_tipo) || empty($_tipo) ){
                $tipo = explode('|', $_tipo);
                if(in_array(array('CF','AL','JT'), $tipo)){
                    $arrancha = array(
                        'fdt_r' => date("d/m/Y", strtotime($data_arrancha)),
                        'fcf' => in_array('CF', $tipo)?'1':'0', //#cafe
                        'fal' => in_array('AL', $tipo)?'1':'0', //#almoco
                        'fjt' => in_array('JT', $tipo)?'1':'0', //#Janta
                    );
                    $obj->info[] = "[MESSAGE] Tipo de refeição informado é ". in_array('CF', $tipo)?'Café da Manhã':(in_array('AL', $tipo)?'Almoço':(in_array('JT', $tipo)?'Janta':'Outro'));
                }else {
                    $obj->info[] = "[MESSAGE] Nenhuma tipo informado (CF|AL|JT), arranchamento para Almoço (AL) sendo realizado...";
                    $arrancha = array(
                        'fdt_r' => date("d/m/Y", strtotime($data_arrancha)),
                        'fal' => '1'
                    );
                }
            }else{
                $obj->info[] = "[MESSAGE] Tipo de refeição não informada. Utilize CF (cafe da manha), AL (almoço) ou JT (Janta).";
                $obj->info[] = "[MESSAGE] arranchamento para Almoço (AL) sendo realizado...";
                $arrancha = array(
                    'fdt_r' => date("d/m/Y", strtotime($data_arrancha)),
                    'fal' => '1'
                );
            }

            // [0 = Domingo | 1 = Segunda | 2 = Terca | 3 = Quarta | 4 = Quinta | 5 = Sexta | 6 = Sabado]
            $dds = array(0 => "Domingo", 1 => "Segunda-feira", 2 => "Terça-feira", 3 => "Quarta-feira", 4 => "Quinta-feira", 5 => "Sexta-feira", 6 => "Sábado");
            $dia_da_semana = date('w', strtotime($data_arrancha));
            $obj->info[] = "[MESSAGE] A data de {$data_arrancha} é {$dds[$dia_da_semana]}.";

            if($dia_da_semana >= 1 && $dia_da_semana <= 4){
                $obj->success[] = "[SUCCESS] Como data é dia de semana (entre segunda e quinta).";
                $obj->info[] = "[MESSAGE] Iniciando execução de arranchamento para o dia {$data_arrancha}.";

                if(function_exists('file_get_contents')){
                    try {
                        $arrContextOptions = array(
                            "ssl" => array(
                                "verify_peer" => false,
                                "verify_peer_name" => false
                            ),
                            "http" => array(
                                "ignore_errors" => true,
                                "method" => "POST",
                                "content" => $arrancha,
                                "header" => "Accept-language: en\r\n" .
                                    (!empty($cookie) ? "Cookie: ". implode('; ', (array)$cookie) ."\r\n" : ''),
                            )
                        );
                        $context = stream_context_create($arrContextOptions);
                        $result = file_get_contents($pagina, false, $context);

                        if($result){
                            $status_line = $http_response_header[0];
                            preg_match('{HTTP\/\S*\s(\d{3})}', $status_line, $match);

                            $obj->methodo = 'file_get_contents';
                            $obj->result = $result;
                            $obj->statuscode = $match[1];
                            $obj->success[] = "[SUCCESS] Arranchamento realizado.";
                        }
                    } catch (Exception $e) {
                        $obj->error[] = $e->getMessage();
                    }
                }

                if((!isset($dbg->result) || empty($dbg->result)) && function_exists('fopen')){
                    $arrContextOptions = array(
                        "ssl" => array(
                            "verify_peer" => false,
                            "verify_peer_name" => false,
                        ),
                        "http" => array(
                            "method" => "POST",
                            "content" => $arrancha,
                            "header" => "Accept-language: en\r\n" .
                                (!empty($cookie) ? "Cookie: ".implode('; ', (array)$cookie)."\r\n" : ''),
                        )
                    );

                    $context = stream_context_create($arrContextOptions);
                    $fp = fopen($pagina, 'rb', false, $context);
                    if ($fp){
                        $response = stream_get_contents($fp);
                        if ($response !== false){
                            $stream = stream_get_meta_data($fp);
                            preg_match('{HTTP\/\S*\s(\d{3})}', $stream['wrapper_data'][0], $match);
                            $obj->result = $response;
                            $obj->statuscode = $match[1];
                            $obj->methodo = 'fopen';
                            $obj->success[] = "[SUCCESS] Arranchamento realizado.";
                        }
                    } 
                }

                if((!isset($obj->result) || empty($obj->result)) && function_exists('curl_init')){
                    $curl = curl_init();
                    curl_setopt($curl, CURLOPT_COOKIEFILE, $cookie_file );
                    curl_setopt($curl, CURLOPT_URL, $pagina);
                    curl_setopt($curl, CURLOPT_POST, 1);
                    curl_setopt($curl, CURLOPT_HEADER, true);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $arrancha );
                    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($curl, CURLOPT_VERBOSE,false);

                    $result = curl_exec($curl);
                    if(!$result)$obj->error[] = "[ERROR] Falha de conexão com o Servidor.";
                    else if (curl_error($curl))$obj->error[] = "[ERROR] Erro do CURL" . curl_error($curl);
                    else{
                        $obj->result = $result;
                        $obj->statuscode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                        $obj->methodo = 'curl';
                        $obj->success[] = "[SUCCESS] Arranchamento realizado.";
                    }
                }

                if(isset($obj) && !empty($obj->result)){
                    try{
                        require('simple_html_dom.php');
                        $html = str_get_html($obj->result);
                        if(!empty($request)){
                            $arranchados = array();
                            foreach($html->find('table') as $rw){
                                foreach($rw->find('tr') as $row) {
                                    try {
                                        $arranchado = new stdClass();
                                        $arranchado->nome = trim(strip_tags($row->find('td',0)->plaintext)) == 1?true:false;
                                        $arranchado->cafe = trim(strip_tags($row->find('td',1)->plaintext)) == 1?true:false;
                                        $arranchado->almoco = trim(strip_tags($row->find('td',2)->plaintext)) == 1?true:false;
                                        $arranchado->janta = trim(strip_tags($row->find('td',3)->plaintext)) == 1?true:false;
                                        $obj->arranchados[] = $arranchado;
                                    } catch (PDOException $ex) {
                                        $obj->error[] = $ex->getMessage();
                                    }
                                }
                            }
                        }
                    }catch(Exception $e) {
                        $obj->error[] = "[ERROR] Ao coletar dados de retorno do arranchamento.";
                        $obj->error[] = $e->getMessage();
                    }
                }

            }
        }

    }catch(Exception $e) {
        $obj->error[] = "[ERROR] Ao realizar o arranchamento para {$user}.";
        $obj->error[] = $e->getMessage();
    }
    return $obj;
}


$object = new stdClass();
if(isset($argv)){
    for ($i=1; $i < $argc; $i++) {
        parse_str($argv[$i]);
    }
    $object->login = login($usuario, $senha);
}else if(isset($_REQUEST)){
    $object->login = login($_REQUEST['usuario'], $_REQUEST['senha']);
    
}else $obj->error[] = "[ERROR] Sem parametros para buscar as informações, utilize: ?data=DD-MM-YY&tipo=CF|AL|JT";
print_r($object);
//echo json_encode($obj);
?>
