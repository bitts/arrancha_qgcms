 <?php

/** ***********************************************************************************************************************************************
 Script para realização de arranchamentos automáticos no sistema SILA utilizado no rancho do QGCMS - POA/RS
 - No linux colocar script no agendamento cron para execuções diárias onde a data definida deve ser da seguinte maneira
	 ... -data=$(date -d "tomorrow")-$(date +%m)-$(date +%y) -tipo=CF|AL|JT -retorno=cli
 	Dica: enviar e-mail de confirmacao utilizando biblioteca mail
 	 ... -tipo=CF|AL|JT -retorno=cli | echo "this is the body" | mail -s "this is the subject" "to@address"
 - Script pode ser utilizado via webservice em servidor web podendo ser criado um APP utilizando as chamadas para a realizacao dos arranchamentos

 OBS.:
   Dependências de biblioteca:
     (Install cURL on Ubuntu)
        sudo add-apt-repository ppa:ondrej/php
	sudo apt-get update && sudo apt-get install php7.4 php7.4-curl
     (Install curl on Fedora/RHEL/CentOS)
	yum install curl
	dnf -y install php-curl
     (Install windows)
	Open php.ini file from directory: C:\wamp\bin\apache\Apache2.2.21\bin directory.
	Enable php_curl.dll extension. This can be enabled by removing ';' extension=php_curl.dll
*/

ini_set('display_errors',1);

ini_set('memory_limit', '256M');
ini_set('max_execution_time', 3600);

DEFINE("DEBUG",true);

if(DEBUG){
    ini_set('display_errors',1);
    ini_set('display_startup_erros',1);
    error_reporting(E_ERROR | E_ERROR | E_NOTICE);
}

function progress_bar($done, $total, $info="", $width=50) {
    $perc = round(($done * 100) / $total);
    $bar = round(($width * $perc) / 100);
    return sprintf("%s%%[%s>%s]%s\r", $perc, str_repeat("=", $bar), str_repeat(" ", $width-$bar), $info);
}

function login($_user, $_pswd){
    $obj = new stdClass();
    $cli = array();
    try{
        if(!isset($_user) && empty($_user))$cli[] = $obj->error[] = "[ERROR] Necessário informar USUARIO do Sila.";
        if(!isset($_pswd) && empty($_pswd))$cli[] = $obj->error[] = "[ERROR] Necessário informar SENHA de acesso do usuário do Sila.";

        $obj->cookie_file = $cookie_file = "/tmp/cookies_sila_{$_user}.txt";
        if(!file_exists($cookie_file)) {
            $fh = fopen($cookie_file, "w");
            if($fh){
                fwrite($fh, "");
                fclose($fh);
            }else $cli[] = $obj->error[] = "[ERROR] Não foi possível criar arquivo de cookies.";
            if(function_exists('exec'))exec("chmod 777 {$cookie_file}");
            else $cli[] = $obj->error[] = "[ERROR] Não é possível verificar permissão de gravação em disco.";
        }else $cli[] = $obj->info[] = "[MESSAGE] Arquivo {$cookie_file} não encontrado.";

        if(function_exists('is_writable')){
            $cli[] = $obj->info[] = "[MESSAGE] O arquivo de cookies locais {$cookie_file} ". (is_writable($cookie_file)?"SIM":"NÃO") . " possui permissão de escrita.";
        }

        if( (!isset($obj->error) || empty($obj->error)) && (0 == filesize( $cookie_file )) && !empty($_user) && !empty($_pswd)){
            $cli[] = $obj->info[] = "[MESSAGE] O arquivo {$cookie_file} encontra-se vazio e um novo login será realizado.";
            try{

                $obj->data_login = $postfields = array(
                    'fcpf' => $_user,
                    'fsnh' => $_pswd,
                    'acao' => 'login'
                );

                $pagina = "http://sila.3rm.eb.mil.br/login.php";

		$obj->sendContextOptions[] = $arrContextOptions = array(
			"ssl" => array(
				"verify_peer" => false,
				"verify_peer_name" => false
			),
			"http" => array(
				"ignore_errors" => true,
				"method" => "POST",
				"content" => http_build_query($postfields),
				"header" => "Content-Type: application/x-www-form-urlencoded\r\n".
				"User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.129 Safari/537.36\r\n".
				"Content-Length: ".strlen(json_encode($postfields))."\r\n".
				"Host: sila.3rm.eb.mil.br\r\n".
				//"Referer: http://sila.3rm.eb.mil.br/inicial_consulta.php\r\n".
				"Connection: keep-alive\r\n".
				"Accept-Encoding: gzip, deflate\r\n".
				"Accept-Language: pt-BR,pt;q=0.9,en;q=0.8\r\n".
				"Connection: close"
			),
		);

		$context = stream_context_create($arrContextOptions);

		if(function_exists('file_get_contents')){
                    $obj->methodo = 'file_get_contents';

                    $cli[] = $obj->info[] = "[MESSAGE] Metodo utilizado {$obj->methodo}";
                    $result = file_get_contents($pagina, false, $context, -1, 40000);

                    if($result){

                        $status_line = $http_response_header[0];
                        preg_match('{HTTP\/\S*\s(\d{3})}', $status_line, $match);

                        $cookies = array();
                        foreach ($result as $hdr) {
                            if (preg_match('/^Set-Cookie:\s*([^;]+)/', $hdr, $matches)) {
                                parse_str($matches[1], $tmp);
                                $cookies[] = $tmp;
                            }
                        }

                        if( !empty($cookies) ){
                            $obj->cookie_content = $cookie_content = implode('; ', $cookies);
    			    $obj->cookie_array = $cookies;
                            if(false !== file_put_contents($cookie_file, $cookie_content))$cli[] = $obj->error[] = "[ERROR] Não foi possível criar o arquivo de cookies com file_put_contents.";
                            else $cli[] = $obj->success[] = "[SUCCESS] Arquivo de Cookies criado com sucesso utilizando file_put_contents.";
                        }else $cli[] = $obj->error[] = "[ERROR] Não foi possível criar o arquivo de cookies com file_put_contents.";

			$obj->result[] = $result;
			$obj->statuscode = (int)$match[1];

			if($obj->statuscode >= 200 && $obj->statuscode <= 299)$cli[] = $obj->success[] = "[SUCCESS](file_get_contents)[{$obj->statuscode}] Login realizado.";
                        else if($obj->statuscode >= 300 && $obj->statuscode <= 399)$cli[] = $obj->error[] = "[ERROR](file_get_contents)[{$obj->statuscode}] Falha no login, redirecionamento habilitado para esta url [{$pagina}].";
                        else if($obj->statuscode >= 400 && $obj->statuscode <= 499)$cli[] = $obj->error[] = "[ERROR](file_get_contents)[{$obj->statuscode}] Falha no login a nível de cliente.";
                        else if($obj->statuscode >= 500 && $obj->statuscode <= 599)$cli[] = $obj->error[] = "[ERROR](file_get_contents)[{$obj->statuscode}] Falha de login a nível de serviço.";

                        if(!isset($obj->error) || empty($obj->error)){
			    $cookies = array();
			    foreach ($result as $hdr) {
			        if (preg_match('/^Set-Cookie:\s*([^;]+)/', $hdr, $matches)) {
				    parse_str($matches[1], $tmp);
				    $cookies[] = $tmp;
			        }
			    }

			    if( !empty($cookies) ){
				$fh = fopen($cookie_file, "w");
				if($fh){
					$obj->cookie_content = $cookie_content = implode('; ', $cookies);
					if(fwrite($fh, $cookie_content) === FALSE){
						$cli[] = $obj->error[] = "[ERROR] Não foi possível criar arquivo de cookies com fopen.";
					}
					fclose($fh);
				}else $cli[] = $obj->error[] = "[ERROR] Não foi possível criar arquivo de cookies com fopen.";
			    }
			}
                    }
                }else $cli[] = $obj->error[] = "[ERROR] Não é possivel utilizar FILE_GET_CONTENTS para enviar dados para o servidor.";

		if(!isset($obj->result) && function_exists('fopen')){
                    $obj->methodo = 'fopen';
                    $cli[] = $obj->info[] = "[MESSAGE] Metodo utilizado {$obj->methodo}";
                    try{
                        $fp = fopen($pagina, 'rb', false, $context);
                        if ($fp){
                            $result = stream_get_contents($fp);
                            if ($result !== false){
                                $stream = stream_get_meta_data($fp);
                                preg_match('{HTTP\/\S*\s(\d{3})}', $stream['wrapper_data'][0], $match);
                                $obj->result[] = $result;
                                $obj->statuscode = (int)$match[1];

				if($obj->statuscode >= 200 && $obj->statuscode <= 299)$cli[] = $obj->success[] = "[SUCCESS](fopen)[{$obj->statuscode}] Login realizado.";
                            	else if($obj->statuscode >= 300 && $obj->statuscode <= 399)$cli[] = $obj->error[] = "[ERROR](fopen)[{$obj->statuscode}] Falha no login, redirecionamento habilitado para esta url [{$pagina}].";
                            	else if($obj->statuscode >= 400 && $obj->statuscode <= 499)$cli[] = $obj->error[] = "[ERROR](fopen)[{$obj->statuscode}] Falha no login a nível de cliente.";
                            	else if($obj->statuscode >= 500 && $obj->statuscode <= 599)$cli[] = $obj->error[] = "[ERROR](fopen)[{$obj->statuscode}] Falha de login a nível de serviço.";

				if(!isset($obj->error) || empty($obj->error)){
					$cli[] = $obj->info[] = "[MESSAGE](fopen) Percorrendo dados em busca de Cookies.";
					$cookies = array();
					foreach ($result as $hdr) {
					    if (preg_match('/^Set-Cookie:\s*([^;]+)/', $hdr, $matches)) {
						parse_str($matches[1], $tmp);
						$cookies[] = $tmp;
					    }
					}

					if(!empty($cookies)){
					    $obj->cookie_array = $cookies;
					    $obj->cookie_content = implode('; ', $cookies);
					    $fp = fopen($cookie_file, "a");
					    $escreve = fwrite($fp, $cookies);
					    if($escreve)$cli[] = $obj->error[] = "[ERROR] Não foi possível criar o arquivo de cookies.";
					    else $cli[] = $obj->success[] = "[SUCCESS] Arquivo de Cookies criado com sucesso.";
					    fclose($fp);
					}else $cli[] = $obj->error[] = "[ERROR] Não foi possível ler conteudo de cookies da requisição e criar o arquivo de cookies local.";
				}
                            }
                        }else fclose($fp);
                    } catch (Exception $e) {
                        $obj->error[] = $e->getMessage();
                    }
                }else $cli[] = $obj->error[] = "[ERROR] Não habilitada a biblioteca cURL do PHP";

		if(!isset($obj->result) && function_exists('curl_init')){
                    $obj->methodo = 'curl';
                    $cli[] = $obj->info[] = "[MESSAGE] Metodo utilizado {$obj->methodo}";
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
                            $cli[] = $obj->error[] = "[ERROR] Falha de conexão com o Servidor.";
                        }else if (curl_error($curl)){
                            $cli[] = $obj->error[] = "[ERROR] Erro do CURL" . curl_error($curl);
                        }else if($result){
                            $obj->result[] = $result;
                            $obj->statuscode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

			    if($obj->statuscode >= 200 && $obj->statuscode <= 299)$cli[] = $obj->success[] = "[SUCCESS](curl)[{$obj->statuscode}] Login realizado.";
                            else if($obj->statuscode >= 300 && $obj->statuscode <= 399)$cli[] = $obj->error[] = "[ERROR](curl)[{$obj->statuscode}] Falha no login, redirecionamento habilitado para esta url [{$pagina}].";
                            else if($obj->statuscode >= 400 && $obj->statuscode <= 499)$cli[] = $obj->error[] = "[ERROR](curl)[{$obj->statuscode}] Falha no login a nível de cliente.";
                            else if($obj->statuscode >= 500 && $obj->statuscode <= 599)$cli[] = $obj->error[] = "[ERROR](curl)[{$obj->statuscode}] Falha de login a nível de serviço.";

			    if(!isset($obj->error) || empty($obj->error)){
				$cookies = array();
				foreach ($result as $hdr) {
					if (preg_match('/^Set-Cookie:\s*([^;]+)/', $hdr, $matches)) {
					    parse_str($matches[1], $tmp);
					    $cookies[] = $tmp;
					}
				}
				if(!empty($cookies)){
					$obj->cookie_content = implode('; ', $cookies);
					$obj->cookie_array = $cookies;
			    	}else $cli[] = $obj->error[] = "[ERROR] Não foi possível criar o arquivo de cookies.";
			    }
                        }
                    } catch (Exception $e) {
                        $obj->error[] = $e->getMessage();
                    } finally {
                        curl_close($curl);
                    }
                }else $cli[] = $obj->info[] = "[MESSAGE] Não é possivel utilizar CURL para enviar dados para o servidor.";

            }catch(Exception $e) {
                $cli[] = $obj->error[] = $e->getMessage();
            }
        }

    }catch(Exception $e) {
        $cli[] = $obj->error[] = $e->getMessage();
    }
    $obj->cli = $cli;
    return $obj;
}

function arranchar( $_obj, $_tipo, $_data ){
    $obj = new stdClass();
    $cli = array();
    try{
        if(isset($_tipo) && !empty($_tipo))$cli[] = $obj->error[] = "[ERROR] Tipo de alimentação não informada - valor default ALMOÇO.";
        if(isset($_data) && !empty($_data)){
            $vlr = new DateTime(date('d-m-Y'));
            $vlr->modify('+1 day');
            $cli[] = $obj->error[] = "[ERROR] Data de arranchamento não informada - data default AMANHA é: {$vlr->format('d/m/Y')}";
        }

        $cli[] = $obj->info[] = "[SUCCESS] Sistema já logado, enviando requisição de arranchamento...";

        $data_arrancha = $data = null;
        $arrancha = array();
        $obj->requisicao_arrancha = $pagina = 'http://sila.3rm.eb.mil.br/aprov/reverte2.php';

        if(!isset($_data) || empty($_data) ){
            $data = new DateTime(date('d-m-y'));
            $data->modify('+1 day');
            $data_arrancha = $data->format('d-m-y');
            $cli[] = $obj->info[] = "[MESSAGE] Data não informada. Definido por default a data do dia seguinte {$data_arrancha}.";
        }else{
            $cli[] = $obj->info[] = "[MESSAGE] Data informada por parametro é {$_data}.";
            if(preg_match("/^\d{2}-(0[1-9]|1[0,1,2])-(0[1-9]|[1,2][0-9]|3[0,1])$/",$_data)){
                $data = new DateTime(date($_data));
                $data_arrancha = $data->format('d-m-y');
                $cli[] = $obj->info[] = "[MESSAGE] Data formatada para {$data_arrancha} (padrão de data do SILA).";
            }else {
                $data = new DateTime(date('d-m-Y'));
                $data_arrancha = $data->format('d-m-Y');
                $cli[] = $obj->error[] = "[ERROR] Data informada no formato errado, utilize no padrão dd-mm-yy (Ex.: 13-06-21). Definido por default a data {$data_arrancha}.";
            }
        }

        if(isset($data_arrancha) && !empty($data_arrancha)){
            $cli[] = $obj->info[] = "[MESSAGE] Tipo de refeição informada por parametro é {$_tipo}.";
            if(!isset($_tipo) || empty($_tipo) ){
                $tipo = explode('|', $_tipo);
                if(in_array(array('CF','AL','JT'), $tipo)){
                    $arrancha = array(
                        'fdt_r' => date("d/m/Y", strtotime($data_arrancha)),
                        'fcf' => in_array('CF', $tipo)?'1':'0', //#cafe
                        'fal' => in_array('AL', $tipo)?'1':'0', //#almoco
                        'fjt' => in_array('JT', $tipo)?'1':'0', //#Janta
                    );
                    $cli[] = $obj->info[] = "[MESSAGE] Tipo de refeição informado é ". in_array('CF', $tipo)?'Café da Manhã':(in_array('AL', $tipo)?'Almoço':(in_array('JT', $tipo)?'Janta':'Outro'));
                }else {
                    $cli[] = $obj->info[] = "[MESSAGE] Nenhuma tipo informado (CF|AL|JT), arranchamento para Almoço (AL) sendo realizado...";
                    $arrancha = array(
                        'fdt_r' => date("d/m/Y", strtotime($data_arrancha)),
                        'fal' => '1'
                    );
                }
            }else{
                $cli[] = $obj->info[] = "[MESSAGE] Tipo de refeição não informada. Utilize CF (cafe da manha), AL (almoço) ou JT (Janta).";
                $cli[] = $obj->info[] = "[MESSAGE] arranchamento para Almoço (AL) sendo realizado...";
                $arrancha = array(
                    'fdt_r' => date("d/m/Y", strtotime($data_arrancha)),
                    'fal' => '1'
                );
            }

            // [0 = Domingo | 1 = Segunda | 2 = Terca | 3 = Quarta | 4 = Quinta | 5 = Sexta | 6 = Sabado]
            $dds = array(0 => "Domingo", 1 => "Segunda-feira", 2 => "Terça-feira", 3 => "Quarta-feira", 4 => "Quinta-feira", 5 => "Sexta-feira", 6 => "Sábado");
            $dia_da_semana = date('w', strtotime($data_arrancha));
            $cli[] = $obj->info[] = "[MESSAGE] A data de {$data_arrancha} é {$dds[$dia_da_semana]}.";

            if($dia_da_semana >= 1 && $dia_da_semana <= 4){
                $cli[] = $obj->success[] = "[SUCCESS] Como data é dia de semana (entre segunda e quinta).";
                $cli[] = $obj->info[] = "[MESSAGE] Iniciando execução de arranchamento para o dia {$data_arrancha}.";
		$cookie = (isset($_obj->cookie_array) && !empty($_obj->cookie_array))?$_obj->cookie_array:null;
		$obj->sendContextOptions[] = $arrContextOptions = array(
			"ssl" => array(
				"verify_peer" => false,
				"verify_peer_name" => false
			),
			"http" => array(
				"ignore_errors" => true,
				"method" => "POST",
				"content" => $arrancha,
				"header" => "POST /aprov/reverte2.php HTTP/1.1\r\n".
				"Host: sila.3rm.eb.mil.br\r\n".
				"Connection: keep-alive\r\n".
				"Content-Length: ". strlen(json_encode($arrancha)). "\r\n".
				"Cache-Control: max-age=0\r\n".
				"Upgrade-Insecure-Requests: 1\r\n".
				"Origin: http://sila.3rm.eb.mil.br\r\n".
				"Content-Type: application/x-www-form-urlencoded\r\n".
				"User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.129 Safari/537.36\r\n".
				"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/\*;q=0.8,application/signed-exchange;v=b3;q=0.9\r\n".
				"Referer: http://sila.3rm.eb.mil.br/inicial_consulta.php\r\n".
				"Accept-Encoding: gzip, deflate\r\n".
				"Accept-Language: pt-BR,pt;q=0.9,en;q=0.8\r\n".
				((!empty($cookie)) ? ("Cookie: ". implode('; ', (array)$cookie) ."\r\n") : '') .
				"Connection: close"
			)
		);

		$context = stream_context_create($arrContextOptions);
                if(function_exists('file_get_contents')){
                    try {
                        $result = file_get_contents($pagina, false, $context);
                        if($result){
                            $status_line = $http_response_header[0];
                            preg_match('{HTTP\/\S*\s(\d{3})}', $status_line, $match);
                            $obj->statuscode = (int)$match[1];

                            $obj->methodo = 'file_get_contents';

			    if($obj->statuscode >= 200 && $obj->statuscode <= 299){
				$cli[] = $obj->success[] = "[SUCCESS](file_get_contents)[{$obj->statuscode}] Arranchamento realizado.";
				$obj->result = $result;
			    }else if($obj->statuscode >= 300 && $obj->statuscode <= 399)$cli[] = $obj->error[] = "[ERROR](file_get_contents)[{$obj->statuscode}] Erro no login a redirecionamento habilitado.";
			    else if($obj->statuscode >= 400 && $obj->statuscode <= 499)$cli[] = $obj->error[] = "[ERROR](file_get_contents)[{$obj->statuscode}] Erro no login a nível de cliente.";
			    else if($obj->statuscode >= 500 && $obj->statuscode <= 599)$cli[] = $obj->error[] = "[ERROR](file_get_contents)[{$obj->statuscode}] Erro no login a nível de serviço.";
                        }
                    } catch (Exception $e) {
                        $obj->error[] = $e->getMessage();
                    }
                }else $cli[] = $obj->error[] = "[ERROR](file_get_contents) Não habilitada a biblioteca file_get_contents do PHP.";

                if((!isset($dbg->result) || empty($dbg->result)) && function_exists('fopen')){
    		    try {
                        $fp = fopen($pagina, 'rb', false, $context);
                        if ($fp){
                            $response = stream_get_contents($fp);
                            if ($response !== false){
                                $stream = stream_get_meta_data($fp);
                                preg_match('{HTTP\/\S*\s(\d{3})}', $stream['wrapper_data'][0], $match);
                                $obj->statuscode = (int)$match[1];

                                $obj->methodo = 'fopen';

				if($obj->statuscode >= 200 && $obj->statuscode <= 299){
					$cli[] = $obj->success[] = "[SUCCESS](fopen)[{$obj->statuscode}] Arranchamento realizado.";
					$obj->result = $response;
                                }else if($obj->statuscode >= 300 && $obj->statuscode <= 399)$cli[] = $obj->error[] = "[ERROR](fopen)[{$obj->statuscode}] Erro no login, redirecionamento habilitado.";
                                else if($obj->statuscode >= 400 && $obj->statuscode <= 499)$cli[] = $obj->error[] = "[ERROR](fopen)[{$obj->statuscode}] Erro no login a nível de cliente.";
                                else if($obj->statuscode >= 500 && $obj->statuscode <= 599)$cli[] = $obj->error[] = "[ERROR](fopen)[{$obj->statuscode}] Erro no login a nível de serviço.";
                            }
                        }
	    	    } catch (Exception $e) {
                        $obj->error[] = $e->getMessage();
                    }
                }else $cli[] = $obj->error[] = "[ERROR](fopen) Não habilitada a biblioteca fopen do PHP.";

                if((!isset($obj->result) || empty($obj->result)) && function_exists('curl_init')){

		    if(isset($_obj) && isset($_obj->login->cookie_file)){
                        $curl = curl_init();
                        curl_setopt($curl, CURLOPT_COOKIEFILE, $_obj->login->cookie_file );
                        curl_setopt($curl, CURLOPT_URL, $pagina);
                        curl_setopt($curl, CURLOPT_POST, 1);
                        curl_setopt($curl, CURLOPT_HEADER, true);
                        curl_setopt($curl, CURLOPT_POSTFIELDS, $arrancha );
                        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
                        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($curl, CURLOPT_VERBOSE,false);

                        $result = curl_exec($curl);
                        if(!$result)$cli[] = $obj->error[] = "[ERROR] Falha de conexão com o Servidor.";
                        else if (curl_error($curl))$cli[] = $obj->error[] = "[ERROR] Erro do CURL" . curl_error($curl);
                        else{
                            $obj->statuscode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

                            $obj->methodo = 'curl';

		            if($obj->statuscode >= 200 && $obj->statuscode <= 299){
				$cli[] = $obj->success[] = "[SUCCESS](curl)[{$obj->statuscode}] Arranchamento realizado.";
				$obj->result = $result;
			    }else if($obj->statuscode >= 300 && $obj->statuscode <= 399)$cli[] = $obj->error[] = "[ERROR](curl)[{$obj->statuscode}] Erro no login, redirecionamento habilitado.";
                            else if($obj->statuscode >= 400 && $obj->statuscode <= 499)$cli[] = $obj->error[] = "[ERROR](curl)[{$obj->statuscode}] Erro no login a nível de cliente.";
                            else if($obj->statuscode >= 500 && $obj->statuscode <= 599)$cli[] = $obj->error[] = "[ERROR](curl)[{$obj->statuscode}] Erro no login a nível de serviço.";
                        }
		    }else $cli[] = $obj->error[] = "[ERROR](curl) Não foi informado o arquivo para salvar as informações de Cookie do sistema.";
                }else $cli[] = $obj->error[] = "[ERROR](curl) Não habilitada a biblioteca cURL do PHP.";

                if(isset($obj) && !empty($obj->result)){
                    try{
                        require('simple_html_dom.php');
                        $html = str_get_html($obj->result);
                        if(!empty($html)){
                            $arranchados = array();
                            foreach($html->find('table') as $rw){
				$cli[] = "Arranchamentos de Militares de sua Organização Militar\n";
				$cli[] = "Militar   \t      CAFE    \t      ALMOCO  \t      JANTA\n";
                                foreach($rw->find('tr') as $row) {
                                    try {
                                        $arranchado = new stdClass();
                                        $arranchado->nome = strip_tags($row->find('td',0)->plaintext);
                                        $arranchado->cafe = trim(strip_tags($row->find('td',1)->plaintext)) == 1?true:false;
                                        $arranchado->almoco = trim(strip_tags($row->find('td',2)->plaintext)) == 1?true:false;
                                        $arranchado->janta = trim(strip_tags($row->find('td',3)->plaintext)) == 1?true:false;
					if($arranchado->nome !== "NOME"){
						$obj->arranchados[] = $arranchado;
						$str .= "{$arranchado->nome}    \t";
                       				$str .= ($arranchado->cafe == 1)?"X     \t":"   \t";
                                                $str .= ($arranchado->almoco == 1)?"X   \t":"   \t";
                                                $str .= ($arranchado->janta == 1)?"X    \t":"   \t";
                                                $str .= "\n";
						$cli[] = $str;
					}
                                    } catch (PDOException $ex) {
                                        $cli[] = $obj->error[] = $ex->getMessage();
                                    }
                                }
                            }
                        }
                    }catch(Exception $e) {
                        $cli[] = $obj->error[] = "[ERROR] Ao coletar dados de retorno do arranchamento.";
                        $cli[] = $obj->error[] = $e->getMessage();
                    }
                }

            }
        }

    }catch(Exception $e) {
        $cli[] = $obj->error[] = "[ERROR] Ao realizar o arranchamento para {$user}.";
        $cli[] = $obj->error[] = $e->getMessage();
    }
    $obj->cli = $cli;
    return $obj;
}


$_rtn_mtd = (PHP_SAPI === 'cli')?'cli':( (isset($_REQUEST) && !empty($_REQUEST['retorno']))?$_REQUEST['retorno']:'text');

if( (PHP_SAPI === 'cli' || php_sapi_name() == 'cli') && isset($argv) ){
	$_rtn = "\nArranchamentos CLI/PHP Sila QGCMS - Quartel General do Comando Militar do Sul\nCriado por 2º Ten Bittencourt (1º CTA / POA-RS)\n\n";
	$object = new stdClass();
	$usuario = $senha = $retorno = $data = null;
	foreach ( $argv as $arg ){
		unset( $matches );
		if ( preg_match( '/^-retorno=(.*)$/', $arg, $matches ) ){
			$retorno = $matches[1];
			if(!in_array($retorno, array('text','cli')))$_rtn .= "Retorno invalido para esta tipo de apresentação.\n";
			else $retorno = 'cli';
		}
		if ( preg_match( '/^-data=(.*)$/', $arg, $matches ) ){
			$data = $matches[1];
		}
		if ( preg_match( '/^-usuario=(.*)$/', $arg, $matches ) ){
                        $usuario = $matches[1];
                }
		if ( preg_match( '/^-senha=(.*)$/', $arg, $matches ) ){
                        $senha = $matches[1];
                }
		if ( preg_match( '/^-tipo=(.*)$/', $arg, $matches ) ){
                        $tipo = $matches[1];
                }

	}
	if(empty($retorno)){
		$_rtn .= "Selecionado o tipo de retorno padrão CLI.\n";
		$retorno = 'cli';
	}
	if(empty($tipo)){
		$_rtn .= "Definido tipo AL (Almoço) como default.\n";
		$tipo = 'AL';
	}
	if(!empty($usuario) && !empty($senha)){
		$_rtn .= "Realizando o login utilizando credenciais informadas.\n";
		$object->login = login($usuario, $senha);
		if(!empty($data) && !empty($tipo)){
			$_rtn .= "Realizando arranchamento de {$tipo} para o dia {$data}.";
			$object->arranchamento = arranchar($object, $tipo, $data);
		}
	}else $_rtn .= "
		Para realizar arranchamento no sistems SILA é necessário informar parametros ao comando:\n
		\t -usuario={String} *[OBRIGATÓRIO]\n
		\t\t * Usuário utilizado para realizar o ligin no sistema SILA \n
		\t -senha={String} *[OBRIGATÓRIO]\n
		\t\t * Senha alfa-númerica de acesso ao sistema SILA.\n
		\t -tipo={CF|AL|JT} onde:\n
		\t\t CF = CaFé da manhã \n
		\t\t AL = ALmoço (valor default) \n
		\t\t JT = JanTa \n
		\t -retorno={text|cli|html|json}\n
		\t\t * Formas de apresentação das informações de retorno\n
		\t\t text = formato texto\n
		\t\t cli = apresentação para CLI\n
		\t\t html = página HTML\n
		\t\t json = javascript object notation\n
		\n
		\t Exemplo: php ". basename(__FILE__) . " -usuario=1cta-bittencourt -senha=1ctaparaomundo -data=29-06-21 -tipo=CF|AL|JT -retorno=cli
		\n * Acompanhe o projeto em: https://github.com/bitts/arrancha_cms/\n
	";

	//echo $_rtn;
	if(isset($object)){
		foreach($object as $lg){
			if(isset($lg->cli)){
				$dados[] = implode(($_rtn_mtd == 'html')?"<br />\n":"\n", $lg->cli);
			}
			if($lg)$dados[] = "\n";
		}

		//$dados = array();
		//foreach($object->login as $lg){
		//	if(isset($lg->info)){
		//		$dados[] = implode($lg->info);
		//		if($lg)$dados[] = "\n";
		//	}
		//}
		if(isset($dados) && !empty($dados))$_rtn .= implode($dados);
		/*
		foreach($object->arranchamento as $arr){
			print_r($arr);
		}*/
	}
	echo $_rtn;

}else if(isset($_REQUEST)){
    $object->login = login($_REQUEST['usuario'], $_REQUEST['senha']);
    $object->arrancho = arranchar( $object, $_REQUEST['tipo'], $_REQUEST['data'] );
    switch($_REQUEST['retorno']){
	    case 'json': echo json_encode($object);
	    case 'php': echo "<pre>"; print_r($object); echo "</pre>";
    }


}else $obj->error[] = "[ERROR] Sem parametros para buscar as informações, utilize: ?usuario=&senha=&data=DD-MM-YY&tipo=CF|AL|JT&retorno=json";

//
?>

