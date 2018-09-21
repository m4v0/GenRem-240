# GENREM-240

#
![genrem-240](https://user-images.githubusercontent.com/34343415/45891121-20a40200-bd9b-11e8-9ee5-30f2f3942afe.png)
#

# Gerador-de-Arquivos-de-Remessa-Bradesco
Gerador de arquivos de remessa CNAB-240 para o banco Bradesco, este aplicativo consume informações,
diretamente de um banco de dados POSTGRESQL - sistema SAGU 2.

# Introdução
Essa biblioteca foi desenvolvida com a finalidade de ser integrada ao sistema de sagu 2, 
no qual tem como objetivo principal criar arquivo de Remessa CNAB 240 posições Bradesco, 
para que seja processados todos os boletos de cobrança pelo banco.

# Descrição do arquivo de remessa Formato CNAB
 - Registro 0 : Header Label
 - Registro 1 : Transação (Segmentos P, Q e R)
 - Registro 9 : Trailler
 
# Procedimentos para criação do arquivo e envio
 * Procedimentos da Empresa
   Uma vez gerado o arquivo REM/TST poderá ser enviado atraves do aplicativo do Bradesco (NETEmpresa)

 * Exemplo de teste
   * para gerar o arquivo remessa teste
   > php genrem-240 -u=1 -h=localhost -t

   * para gerar o arquivo remessa de produção do mes 10/2018
   > php genrem-240 -u=1 -h=localhost -d=102018

# Nome dos Arquivos Remessa 
Bradesco Net Empresa: 
O Arquivo Remessa deverá ter a seguinte formatação:

CBDDMM??.REM
CB : Cobrança Bradesco
DD : O Dia geração do arquivo
MM : O Mês da geração do Arquivo
?? : variáveis alfanumérico-Numéricas
Ex.: 01, AB, A1 etc.

.Rem : Extensão do arquivo.
Exemplo: CB010501.REM ou CB0105AB.REM ou CB0105A1.REM

Para arquivo remessa para teste, a extensão deverá ser TST.
Exemplo: CB010501.TST, o retorno será disponibilizado como CB010501.RST.
