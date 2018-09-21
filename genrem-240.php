<?php

print "#################################################################\n";
print "#   _____            _____                   ___  _  _    ___   #\n";
print "#  / ____|          |  __ \                 |__ \| || |  / _ \  #\n";
print "# | |  __  ___ _ __ | |__) |___ _ __ ___       ) | || |_| | | | #\n";
print "# | | |_ |/ _ \ '_ \|  _  // _ \ '_ ` _ \     / /|__   _| | | | #\n";
print "# | |__| |  __/ | | | | \ \  __/ | | | | |   / /_   | | | |_| | #\n";
print "#  \_____|\___|_| |_|_|  \_\___|_| |_| |_|  |____|  |_|  \___/  #\n";
print "#  _                 __  __           __      __   ____         #\n";
print "# | |               |  \/  |     /\   \ \    / /  / __ \        #\n";
print "# | |__  _   _      | \  / |    /  \   \ \  / /  | |  | |       #\n";
print "# | '_ \| | | |     | |\/| |   / /\ \   \ \/ /   | |  | |       #\n";
print "# | |_) | |_| |     | |  | |  / ____ \   \  /    | |__| |       #\n";
print "# |_.__/ \__, |     |_|  |_| /_/    \_\   \/      \____/        #\n";
print "#         __/ |                                                 #\n";
print "#        |___/                                                  #\n";
print "#                                                       v.1.0.4 #\n";
print "#################################################################\n";
print "# Gerador de arquivo de remessa padrão CNAB-240 Bradesco        #\n";
print "# Criado por MAVO                                               #\n";
print "# Data 03/05/2018                                               #\n";
print "# OpenSource: PHP+POSTGRESQL                                    #\n";
print "#################################################################\n\n";

$unitSel  = '';
$dbhost   = '127.0.0.1';
$dateGen  = '';
$limitrec = '0';
$ndayGen  = '0';
$fieldrec = 'F.maturitydate';

if (($argc > 7) OR ($argc < 2)) {
	displayHelp();
	exit;
}

for ($x = 1; $x < $argc; $x++) {
  // echo $argv[$x] . "\n";
  if ($argv[$x] == '--help') {
    displayHelp();
    exit;
  } else if (substr($argv[$x], 0, 2) == '-u') {
    $unitSel = substr($argv[$x],3);
  } else if (substr($argv[$x], 0, 2) == '-h') {
    $dbhost = substr($argv[$x],3);
  } else if (substr($argv[$x], 0, 2) == '-d') {
    $dateGen  = substr($argv[$x],3);
  } else if (substr($argv[$x], 0, 2) == '-n') {
    $ndayGen = substr($argv[$x],3);
  } else if (substr($argv[$x], 0, 2) == '-t') {
    $limitrec = '10';
  }
}

// Atribui o conteúdo do arquivo para variável $arquivo
// Decodifica o formato JSON e retorna um Objeto
$unitCompanies = json_decode(file_get_contents('./conf/contas.json'));

// Configura Timezone para Horário padrão Fortaleza.
date_default_timezone_set('America/Fortaleza');

// Define o nome do arquivo.
$dayfile    = date("d");
$monthfile  = date("m");
$yearfile   = date("Y");
$separate   = '/';

$timefile   = date('His');
$tagfile    = 'CB';
$creatfile  = 'A' . $unitSel;
$extfile    = ($limitrec == 10 ? '.TST' : '.REM');
$pathfile   = (is_dir("/mnt/remessa-bradesco") ? "/mnt/remessa-bradesco/" : "./foutput/");
$dateGen    = (empty($dateGen) ? $monthfile . $yearfile : $dateGen);

if ($dateGen != 'daily' AND $dateGen != 'week') {
  $dayfile    = '01';
  $monthfile  = substr($dateGen,0,2);
  $yearfile   = substr($dateGen,2,4);
  $dateStart  = $dayfile . $separate . $monthfile . $separate . $yearfile;
  $dateFinish = date("t", mktime( 0, 0, 0, $monthfile, '01', $yearfile)) . $separate  . $monthfile . $separate . $yearfile;
  $dateWhere  = "$fieldrec BETWEEN '$dateStart' AND '$dateFinish'";

} else if ($dateGen == 'daily') {
  $fieldrec   = "F.emissiondate";
  $dayfile    = str_pad(((int)$dayfile + (int)$ndayGen), 2, "0", STR_PAD_LEFT);
  $dateStart  = $dayfile . $separate . $monthfile . $separate . $yearfile;
  $dateFinish = $dateStart;
  $dateWhere  = "$fieldrec = '$dateStart'";

} else if ($dateGen == 'week') {
  $fieldrec    = "F.emissiondate";
  $dateFinish  = $dayfile . $separate . $monthfile . $separate . $yearfile;

  if ((int)$ndayGen >= 0) {
    $dateStart  = $dateFinish;
    $dateFinish = date('d/m/Y', strtotime("+" . $ndayGen . " days"));

  } else if((int)$ndayGen < 0) {
    $ndayGen    = -$ndayGen;
    $dateStart  = date('d/m/Y', strtotime("-" . $ndayGen . " days"));
    $dateFinish = $dateFinish;

  }

  $dateWhere  = "$fieldrec BETWEEN '$dateStart' AND '$dateFinish'";

}

// Cria o Nome do ARQUIVO REMESSA que será verificado.
$fileName    = $pathfile . $tagfile . $dayfile . $monthfile . $creatfile . $extfile;
$fileNameFld = $tagfile . $dayfile . $monthfile . $creatfile . $extfile;

// Define o acesso ao banco de dados.
$dbport    = "5432";
$dbname    = "dbName";
$dbuser    = "postgres";
$dbpwds    = "postgres";
$conn      = @pg_connect("host=$dbhost port=$dbport dbname=$dbname user=$dbuser password=$dbpwds");

if ($conn) {
	print "Successfully connected to: " . pg_host($conn) . "\n\n";
} else {
	print "Unsuccessfully connected to: " . $dbhost . "\n";
	print "Check your connection to the database...\n\n";
    exit;
}

// Cria SQL para verificar se o arquivo de remessa já foi processado.
$cmdSql   = "SELECT count(*) as recno FROM finremittancefile WHERE filename = '$fileNameFld'";
$result   = pg_query($conn, $cmdSql);
$row      = pg_fetch_assoc($result, 0);
$nRecno   = $row['recno'];

if ($nRecno == 0) {

  // Cria SQL para coletar as informações para gerar arquivo de remessa.
  $unitWhere  = '';

  if ($unitSel > 0) {
    $unitWhere = "AND F.unitid = $unitSel";
  }

  if ($limitrec > 0) {
    $unitWhere .= "AND ((E.discount::numeric(14,2) * F.value) / 100) > 0 ";
  }

  // Consulta SQL para obter as informação dos boletos
  $sqlCmd      = "SELECT
                    F.unitid AS unidade,
                    F.personid AS matricula,
                    SUBSTRING(P.NAME, 1, 38) AS sacado,
                    returnTextAsInteger(D.CONTENT) AS cpf,
                    P.LOCATION AS endereco,
                    P.NUMBER AS numero,
                    P.neighborhood AS bairro,
                    SUBSTRING(REPLACE(P.zipcode, '-', ''), 1, 5) AS cep1,
                    LPAD(SUBSTRING(REPLACE(P.zipcode, '-', ''), 6, 3) :: TEXT,3,'0') AS cep2,
                    C.NAME AS cidade,
                    C.stateid AS uf,
                    F.invoiceid AS nossonumero,
                    F.bankinvoiceid AS numerodobanco,
                    TO_CHAR(F.emissiondate, 'DDMMYYYY') AS datadodocumento,
                    TO_CHAR(F.maturitydate, 'DDMMYYYY') AS datadevencimento,
                    TO_CHAR(F.maturityDate - E.daystodiscount,'DDMMYYYY') AS data_limite_desconto,
                    returnTextAsInteger(F. VALUE :: NUMERIC(14, 2) :: TEXT) AS valor,
                    returnTextAsInteger(E.fine :: NUMERIC(14, 2) :: TEXT) AS juros,
                    returnTextAsInteger((((E.discount :: NUMERIC(14, 2) * F.VALUE) / 100) :: NUMERIC(14, 2)) :: TEXT) AS valor_desconto,
                    returnTextAsInteger(((((F. VALUE) :: NUMERIC(14, 2) *(E.monthlyinterest / 100)) / 30) :: NUMERIC(14, 2)) :: TEXT) AS juros_ao_mes,
                    returnTextAsInteger((((F. VALUE) :: NUMERIC(14, 2) * E.fine) / 100) :: NUMERIC(14, 2) :: TEXT) AS valor_da_multa
                  FROM
                    ONLY fininvoice F
                    INNER JOIN ONLY finPolicy E                ON (E.policyId = F.policyId)
                    INNER JOIN ONLY basphysicalpersonstudent P ON (P .personid = F.personid)
                    INNER JOIN ONLY basdocument D              ON (D.personid = F.personid AND D.documentTypeId = '2')
                    INNER JOIN ONLY bascity C                  ON (C .cityid = P .cityid)
                    INNER JOIN ONLY finbankaccount B           ON (B.bankaccountid = F.bankaccountid)
                  WHERE
                    $dateWhere
                    AND B.description LIKE 'BRADESCO%'
                    AND NOT F.bankInvoiceId IS NULL
                    AND F.VALUE > 50
                    $unitWhere
                    AND (SELECT IB.balance FROM fininvoicebalance IB WHERE IB.invoiceid = F.invoiceid) > 0
	                  AND (SELECT filename FROM finremittanceinfos S WHERE S.invoiceid = F.invoiceid) IS NULL
                  ORDER BY
                    F.unitid,
                    F.maturitydate,
                    F.personid,
                    F.invoiceid";

  if ($limitrec > 0) {
    $sqlCmd .= " LIMIT $limitrec";
  }

  $result = pg_query($conn, $sqlCmd);

  $num_rows = pg_num_rows($result);

  print "Loading filter query \"$dateWhere\" register found (" . $num_rows . ")...\n\n";

  if ($num_rows == 0) {
    print "0 records found this query...\n";

  } else {
    print "Creating file ...\n";

    include 'src/240/Arquivo.php';

    //configurando o arquivo de remessa
    $config['codigo_banco']       = $unitCompanies->Company[ $unitSel - 1 ]->bank_code;
    $config['nome_do_banco']      = $unitCompanies->Company[ $unitSel - 1 ]->bank_name;
    $config['razao_social']       = $unitCompanies->Company[ $unitSel - 1 ]->company_name;
    $config['inscricao_empresa']  = $unitCompanies->Company[ $unitSel - 1 ]->company_registration;
    $config['codigo_agencia']     = $unitCompanies->Company[ $unitSel - 1 ]->agency_number;
    $config['digito_agencia']     = $unitCompanies->Company[ $unitSel - 1 ]->agency_digit;
    $config['codigo_da_conta']    = $unitCompanies->Company[ $unitSel - 1 ]->current_account;
    $config['digito_da_conta']    = $unitCompanies->Company[ $unitSel - 1 ]->current_digit;
    $config['digito_ag_conta']    = '';
    $config['codigo_carteira']    = $unitCompanies->Company[ $unitSel - 1 ]->wallet_code;
    $config['codigo_empresa']     = $unitCompanies->Company[ $unitSel - 1 ]->business_code;
    $config['codigo_lote']        = ($limitrec == 10 ? '1' : intval($monthfile . substr($yearfile,2,2)));
    $config['codigo_cedente']     = $config['codigo_empresa'];
    $config['numero_remessa']     = $config['codigo_lote'];
    $config['data_gravacao']      = $dayfile . $monthfile . $yearfile;
    $config['hora_gravacao']      = $timefile;
    $config['densidade_gravacao'] = '06250';

    $arquivo = new Arquivo();

    //configurando remessa
    $arquivo->config($config);
    $nRecno = 0;
    $cmdSql = array();

    $row = pg_fetch_assoc($result);

    while ($nRecno < $num_rows) {
      $row = pg_fetch_assoc($result, $nRecno);

      $cmdSql[] = "INSERT INTO finremittanceinfos (ipaddress, filename, invoiceid, bankinvoiceid, returncode, occurrencecode) VALUES ('127.0.0.1', '$fileNameFld', '" . $row['nossonumero'] . "', '" . $row['numerodobanco'] . "', '01', '00');";

      //adicionando boleto - Informações do Financeiro.
      $boleto['agencia']                      = $config['codigo_agencia'];
      $boleto['agencia_dv'] 					        = $config['digito_agencia'];
      $boleto['conta'] 						            = $config['codigo_da_conta'];
      $boleto['conta_dv'] 					          = $config['digito_da_conta'];
      $boleto['carteira'] 					          = $config['codigo_carteira'];
      $boleto['codigo_empresa']               = $config['codigo_empresa'];
      $boleto['codigo_cedente']               = $config['codigo_cedente'];
      $boleto['numero_controle']              = $monthfile . $yearfile;
      $boleto['inscricao_empresa'] 			      = $config['inscricao_empresa'];
      $boleto['lote_de_servico'] 			        = $config['numero_remessa'];

      // daqui pra frente informação do SAGU2
      $boleto['invoiceId']    				        = $row['nossonumero'];
      $boleto['nosso_numero'] 				        = $row['numerodobanco'];
      $boleto['numero_documento'] 			      = $row['numerodobanco'];
      $boleto['vencimento']                   = $row['datadevencimento'];
      $boleto['data_emissao_titulo']          = $row['datadodocumento'];
      $boleto['data_limite_desconto']         = $row['data_limite_desconto'];
      $boleto['valor']                        = $row['valor'];
      $boleto['valor_desconto']               = $row['valor_desconto'];
      $boleto['juros_ao_mes']                 = $row['juros_ao_mes'];
      $boleto['valor_da_multa']             	= $row['valor_da_multa'];
      $boleto['valor_iof']                    = '0';
      $boleto['tipo_inscricao_pagador'] 		  = '1';
      $boleto['codigo_protesto']              = '1';
      $boleto['num_dia_protesto']             = '30';
      $boleto['numero_inscricao']            	= $row['cpf'];
      $boleto['matricula']                    = $row['matricula'];
      $boleto['nome_pagador']                 = $row['sacado'];
      $boleto['endereco_pagador']             = $row['endereco'];
      $boleto['bairro_pagador']               = $row['bairro'];
      $boleto['cidade_pagador']               = $row['cidade'];
      $boleto['uf_pagador']                   = $row['uf'];
      $boleto['cep_pagador']                  = $row['cep1'];
      $boleto['sufixo_cep_pagador']           = $row['cep2'];
      $boleto['primeira_mensagem']            = '';
      $boleto['sacador_segunda_mensagem']     = '';

      //adicionando boleto
      $arquivo->add_boleto($boleto);
      $nRecno++;
    }

    print "Saving File ..............................: " . $fileName . "\n";
    print "Código da Unidade ........................: " . $unitSel . "\n";
    print "Razão Social .............................: " . $config['razao_social'] . "\n";
    print "CNPJ .....................................: " . $config['inscricao_empresa'] . "\n";
    print "Agente Financeiro ........................: " . $config['codigo_banco'] . " - " . $config['nome_do_banco'] . "\n";
    print "Número de registros encontrados ..........: " . $arquivo->count_detalhes() . "\n";
		print "Valor Total da REMESSA ...................: " . $arquivo->getRemittanceTotal() . "\n";;
    print "Salvando informações no banco de dados ...: ";

    $arquivo->setFilename("$fileName");
    $arquivo->save();

    $cmdSql[] = "INSERT INTO finremittancefile (ipaddress, filename, unitid) VALUES ('127.0.0.1', '$fileNameFld', '$unitSel');";

    for ($i=0; $i < count($cmdSql); $i++) {
      $result = pg_query($conn, $cmdSql[$i]);
      // echo $cmdSql[$i] . "\n";
    }

    echo ($i - 1) . " Registro(s) Gravado(s).\n";

    $fileNameExcel = $arquivo->getFilename() . ".csv";
    print "Salvando informações no arquivo excel ....: $fileNameExcel ";

    $cmdSql = "SELECT
                F.unitid AS unidade,
                F.personid AS matricula,
                SUBSTRING(P.NAME, 1, 38) AS sacado,
                returnTextAsInteger(D.CONTENT) AS cpf,
                P.LOCATION AS endereco,
                P.NUMBER AS numero,
                P.neighborhood AS bairro,
                P.zipcode AS cep,
                C.NAME AS cidade,
                C.stateid AS uf,
                F.invoiceid AS nossonumero,
                F.bankinvoiceid AS numerodobanco,
                TO_CHAR(F.emissiondate, 'DD/MM/YYYY') AS datadodocumento,
                TO_CHAR(F.maturitydate, 'DD/MM/YYYY') AS datadevencimento,
                TO_CHAR(F.maturityDate - E.daystodiscount,'DD/MM/YYYY') AS data_limite_desconto,
                REPLACE((F.VALUE::NUMERIC(14, 2))::TEXT, '.', ',') AS valor,
                REPLACE((E.fine :: NUMERIC(14, 2))::TEXT, '.', ',') AS juros,
                REPLACE(((((E.discount :: NUMERIC(14, 2) * F.VALUE) / 100) :: NUMERIC(14, 2)))::TEXT, '.', ',') AS valor_desconto,
                REPLACE((((((F. VALUE) :: NUMERIC(14, 2) * (E.monthlyinterest / 100)) / 30) :: NUMERIC(14, 2)))::TEXT, '.', ',') AS juros_ao_mes,
                REPLACE(((((F. VALUE) :: NUMERIC(14, 2) * E.fine) / 100) :: NUMERIC(14, 2))::TEXT, '.', ',') AS valor_da_multa
              FROM
                ONLY fininvoice F
                INNER JOIN ONLY finPolicy E                ON (E.policyId = F.policyId)
                INNER JOIN ONLY basphysicalpersonstudent P ON (P .personid = F.personid)
                INNER JOIN ONLY basdocument D              ON (D.personid = F.personid AND D.documentTypeId = '2')
                INNER JOIN ONLY bascity C                  ON (C .cityid = P .cityid)
                INNER JOIN ONLY finbankaccount B           ON (B.bankaccountid = F.bankaccountid)
                INNER JOIN ONLY finremittanceinfos R       ON (R.invoiceid = F.invoiceid)
              WHERE
                R.filename = '$fileNameFld'
              ORDER BY
                F.unitid,
                F.maturitydate,
                F.personid,
                F.invoiceid";

    $result   = pg_query($conn, $cmdSql);
    $num_rows = pg_num_rows($result);
    $nRecno   = 0;
    $row      = pg_fetch_assoc($result, $nRecno);
    $text     = "unidade;matricula;sacado;cpf;endereco;numero;bairro;cep;cidade;uf;nosso_numero;numero_do_banco;data_de_emissao;data_de_vencimento;data_limite_desconto;valor;juros;valor_desconto;juros_ao_mes;valor_da_multa;\r\n";

    while ($nRecno < $num_rows) {
      $row = pg_fetch_array($result, $nRecno, PGSQL_NUM);
      $dat = "";
      for($i = 0; $i < count($row); $i++) {
        $dat .= $row[$i] . ";";
      }
      $text .= $dat . "\r\n";
      $nRecno++;
    }

    file_put_contents($fileNameExcel, $text);
    echo $nRecno . " Registro(s) Gravado(s).\n";

  }
} else {
  print "Arquivo de REMESSA $fileNameFld já foi processado\n";

}

echo "\n";

if(!pg_close($conn)) {
    print "Failed to close connection to " . pg_host($conn) . ": " . pg_last_error($conn) . "\n\n";
} else {
    print "Successfully disconnected from database\n\n";
}

function displayHelp() {
	print "Com usar o GENREM-240\n";
	print "-u= Código da Unidade\n";
	print "-h= IP do servidor Banco de Dados - SAGU\n";
	print "-d= Mes e Ano para gerar o arquivo Remessa (MMYYYY) ou\n";
	print "-d= daily com a opcão -n=x, indica arquivo Remessa diario\n";
	print "    com X dias antes do vencimento\n\n";
	print "-d= week com a opcão -n=x, indica arquivo Remessa semanal\n";
	print "    com X dias antes da emissão do título\n\n";
	print "-t Gera arquivo de Remessa TESTE com 10 registro\n\n";
	print "Exemplos\n\n";
	print "Gera arquivo Remessa da Unidade 1 e do Mês corrente do dia 1º ao ultimo dia do mês\n";
	print "php genrem-240.php -u=1\n\n";
	print "Gera arquivo Remessa TESTE da Unidade 1 e do Mês corrente do dia 1º ao ultimo dia do mês\n";
	print "php genrem-240.php -u=1 -t\n\n";
	print "Gera arquivo Remessa da unidade do Mês e Ano informado do dia 1º ao ultimo dia do mês\n";
	print "php genrem-240.php -u=1 -d=062018\n\n";
	print "Gera arquivo Remessa da unidade do dia atual mais -n= dias a vencer o título\n";
	print "php genrem-240.php -u=1 -d=daily -n=2\n\n";
}

?>
