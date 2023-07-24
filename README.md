
# arrancha_cms
Bot de arranchamento no sistema SILA

Utilize o arquivo [arrancha_cms.php](https://github.com/bitts/arrancha_qgcms/blob/main/arrancha_cms.php) para execução em modo de produção.

Script para realização de arranchamentos automáticos no sistema SILA utilizado no rancho do QGCMS - POA/RS

Script pode ser utilizado via webservice em servidor web (podendo ser criado um APP que a consome para chamadas) ou via terminal Linux como script PHP (obs.: setar permissão de execução) 


## Chamadas via terminal
 - No linux colocar script no agendamento cron

   Dica 01:
   Para execuções diárias a data pode ser definida da seguinte maneira
     ```
     ...  -data=$(date -d "tomorrow")-$(date +%m)-$(date +%y) -tipo=CF|AL|JT -retorno=cli
     ```

   Dica 02:
   Também é possível enviar e-mail de confirmação adicionando a biblioteca mail para linux
   ```
   ...  -retorno=cli | echo "Arranchamento no dia $(date -d "tomorrow")/$(date +%m)/$(date +%Y) realizado!" | mail -s "[ARRANCHAMENTOS] BotSILA" "meu_email@exercito.eb.mil.br"
   ```
 

 OBS.:
   Dependências de biblioteca:
   
   - Install cURL on Ubuntu
     
        ``` 
        sudo add-apt-repository ppa:ondrej/php
        sudo apt-get update && sudo apt-get install php7.4 php7.4-curl
        ```
   - Install curl on Fedora/RHEL/CentOS
     
        ```
	      yum install curl
	      dnf -y install php-curl
        ```
        
   - Install Windows

      Open php.ini file from directory: C:\wamp\bin\apache\Apache2.2.21\bin directory.
     
      Enable php_curl.dll extension. This can be enabled by removing ';' extension=php_curl.dll
