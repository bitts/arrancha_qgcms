<?php

/**
* Create by Bitts in 2019
* codigo somente para testes, utilize outro script para producao
* NAO UTILIZAR - SOMENTE TESTES
*/
session_start();

ini_set('memory_limit', '256M');

DEFINE("DEBUG",true);

if(DEBUG){
        ini_set('display_errors',1);
        ini_set('display_startup_erros',1);
        error_reporting(E_ERROR | E_ERROR | E_NOTICE);
}

$cookie_file = '/tmp/cookies_sila.txt';
$pagina = "http://sila.3rm.eb.mil.br/login.php";
$usuario = 'usuario_cadastrado';
$senha = 'senha_de_acesso';
$datas = array('24-05-2021','25-05-2021','26-05-2021','27-05-2021','31-05-2021','01-06-2021','02-06-2021','03-06-2021');


if(!file_exists($cookie_file)) {
        $fh = fopen($cookie_file, "w");
        if($fh){
                fwrite($fh, "");
                fclose($fh);
        }
        exec("chmod 777 $cookie_file");
}

if(function_exists('is_writable'))
echo "O arquivo de cookies locais {$cookie_file} ". (is_writable($cookie_file)?"SIM":"NÃO") . " possui permissão de escrita.\n";


if( 0 == filesize( $cookie_file ) ){
        echo "O arquivo {$cookie_file} encontra-se fazio e um novo login será realizado.\n";
        try{
                $postfields = array(
                        'fcpf' => $usuario,
                        'fsnh' => $senha,
                        'acao' => 'login'
                );
                $postfields = http_build_query($postfields);

                $curl = curl_init();
                curl_setopt($curl, CURLOPT_COOKIEJAR, $cookie_file );
//              curl_setopt($curl, CURLOPT_COOKIEFILE, $cookie_file );
                curl_setopt($curl, CURLOPT_URL, $pagina);
                curl_setopt($curl, CURLOPT_HEADER, true);
                curl_setopt($curl, CURLOPT_POST, 1);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $postfields );
                curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
//              ob_start();

                $request = curl_exec($curl);
                
                $resposta_http = curl_getinfo($request, CURLINFO_HTTP_CODE);
                echo ($resposta_http < 200 && $resposta_http > 299)?"Erro ao carregar a página [Codigo: {$resposta_http}].\n":"Página carregada [Codigo: {$resposta_http}].\n";

                if(!$request){
                        echo "Error página não carregada: " . curl_error($curl) .".\n";
                        echo "Code CURL: " . curl_errno($curl);
                }else{
                        if(DEBUG){
                                echo "=========[ INICIO RESPOSTA ]==========\n";
                                print_r($request);
                                echo "=========[ FIM RESPOSTA ]==========\n";
                        }
                }

                if (curl_error($curl)) {
                        echo "Erro do CURL" . curl_error($curl) .".\n";
                }
//              ob_end_clean();
                curl_close($curl);

        }catch(Exception $e) {
            if(DEBUG) print_r($e); //->getMessage();
        }
}else{

        try{
                echo "Sistema já logado, enviando requisição de arranchamento...\n";

                $pagina = 'http://sila.3rm.eb.mil.br/aprov/reverte2.php';

               
                if(!isset($_REQUEST['data']) || empty($_REQUEST['data']) ){
                        $data = new DateTime(date('d-m-Y'));
                        $data->modify('+1 day');
                        $amanha = $data->format('d-m-Y');   //$amanha = date($data, strtotime("+2 days"));

                        echo "Data de amanha é {$amanha}.\n";
                       
                        echo "Verificando datas para arranchamento pre definidas:\n";
                        if(DEBUG) print_r( $datas );

                        $amanha = in_array($amanha, $datas)?$amanha:'';
                }else{
                        echo "Data informada por parametro é {$_REQUEST['data']}.\n";
                        $data = new DateTime(date($_REQUEST['data']));
                        $amanha = $data->format('d-m-Y');
                        echo "Data formatada para {$amanha} padrão de data do SILA.\n";
                }
                
                if(!empty($amanha))echo "A data para o arranchamento é: {$amanha}. \n";
                else echo "Não existe arranchamento a ser realizado. \n";

                if(isset($amanha) && !empty($amanha)){

                        echo "Como a data informada é valida, iniciando processo de arranchamento...\n";
                        // [0 = Domingo | 1 = Segunda | 2 = TerÃ§a | 3 = Quarta | 4 = Quinta | 5 = Sexta | 6 = SÃ¡bado]
                        $dds = array(0 => "Domingo", 1 => "Segunda-feira", 2 => "Terça-feira", 3 => "Quarta-feira", 4 => "Quinta-feira", 5 => "Sexta-feira", 6 => "Sábado");
                        $dia_da_semana = date('w', strtotime($amanha));
                        echo "A data de $amanha é {$dds[$dia_da_semana]}.\n";

                        if($dia_da_semana >= 1 && $dia_da_semana <= 4){
                                echo "Como data é dia de semana (entre segunda e quinta). \n";
                                echo "Iniciando execução de arranchamento para o dia $amanha.  \n";
                                // verificar se Ã© escala preta ou vermelha... (complica) ... pegar via curl dado da pagina do cms escala de serviÃ§o e diferenciar cores da tabela e colegar as datas

                                $postfields = array(
                                        'fdt_r' => date("d/m/Y", strtotime($amanha)),
                                        'fal' => '1' //almoco
                                        //'fcf' => '1'  //#cafe
                                        // 'fal' => '1'  //#almoco
                                        // 'fjt' => '1'  //#Janta
                                );

                                //lendo
                                $curl = curl_init();
                                curl_setopt($curl, CURLOPT_COOKIEFILE, $cookie_file );
                                curl_setopt($curl, CURLOPT_URL, $pagina);
                                curl_setopt($curl, CURLOPT_POST, 1);
                                curl_setopt($curl, CURLOPT_HEADER, true);
                                curl_setopt($curl, CURLOPT_POSTFIELDS, $postfields );
                                curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
                                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($curl, CURLOPT_VERBOSE,false);

                                $request = curl_exec($curl);
                                $resposta_http = curl_getinfo($request, CURLINFO_HTTP_CODE);
                                echo ($resposta_http < 200 && $resposta_http > 299)?"Erro ao carregar a página [Codigo: {$resposta_http}].\n":"Página carregada [Codigo: {$resposta_http}].\n";

                                if(!$request){
                                        echo "Error página não carregada: " . curl_error($curl) .".\n";
                                        echo "Code CURL: " . curl_errno($curl) .".\n";
                                }else{
                                        if(DEBUG) 
                                                echo "=========[ INICIO RESPOSTA ]==========\n";
                                                print_r($request);
                                                echo "=========[ FIM RESPOSTA ]==========\n";
                                        }

                                        require('simple_html_dom.php');
                                        $html = str_get_html($request);
                                        if(!empty($request)){
                                                $arranchados = array();
                                                foreach($html->find('table') as $rw){
                                                        foreach($rw->find('tr') as $row) {
                                                                try {
                                                                        $arranchado = new stdClass();

                                                                        $arranchado->nome = trim(strip_tags($row->find('td',0)->plaintext));
                                                                        $arranchado->cafe = trim(strip_tags($row->find('td',1)->plaintext));
                                                                        $arranchado->almoco = trim(strip_tags($row->find('td',2)->plaintext));
                                                                        $arranchado->janta = trim(strip_tags($row->find('td',3)->plaintext));

                                                                        $arranchados[] = $arranchado;
                                                                } catch (PDOException $ex) {
                                                                        //$ex->getMessage();
                                                                }
                                                        }
                                                }
                                                echo "Arranchados no dia $amanha\n";
                                                echo "Militar   \t      CAFE    \t      ALMOCO  \t      JANTA\n";
                                                foreach($arranchados as $arr){
                                                        if(DEBUG)print_r($arr);
                                                        echo "{$arr->nome}    \t";
                                                        echo ($arr->cafe == 1)?"X     \t":"   \t";
                                                        echo ($arr->almoco == 1)?"X   \t":"   \t";
                                                        echo ($arr->janta == 1)?"X    \t":"   \t";
                                                        echo "\n";
                                                }
                                        }

                                }

                        }
                }

        }catch(Exception $e) {
                if(DEBUG) {
                        echo "Erro de PHP mensagem: \n";
                        print_r($e); //->getMessage();
                        echo "======================================";
                }
        }
        echo "Fim da execução do script.\n";

}

?>
