<?php
namespace App\Service;
use PDO;
trait FinanceMatchingRuleRegisterTrait{
 public static function fetchBankRegister(PDO $pdo,array $f=[]):array{
  $all=[];$page=1;$chunk=500;
  do{$batch=BankFinanceService::getTransactions($pdo,null,$page,$chunk,$f['date_from']??null,$f['date_to']??null);$all=array_merge($all,$batch['data']);$page++;}while(count($all)<(int)$batch['total']);
  $cfu=[];foreach(self::fetchCashFlowCenters($pdo,false) as $x)$cfu[(int)$x['id']]=$x['name'];$dds=[];foreach(FinanceDdsCategoryService::fetchCategories($pdo) as $x)$dds[(int)$x['id']]=$x;
  $status=(string)($f['classification_status']??'');$q=self::lower(trim((string)($f['search']??'')));$rows=[];
  foreach($all as $tx){if($status!==''&&($tx['classification_status']??'UNALLOCATED')!==$status)continue;$cfuName=$cfu[(int)($tx['cash_flow_center_id']??0)]??'';$d=$dds[(int)($tx['dds_category_id']??0)]??[];$hay=self::lower(implode(' ',[$tx['document_number']??'',$tx['counterparty_name']??'',$tx['counterparty_inn']??'',$tx['purpose']??'',$cfuName,$d['name']??'']));if($q!==''&&!str_contains($hay,$q))continue;$tx['cash_flow_center_name']=$cfuName;$tx['dds_category_name']=$d['name']??'';$tx['dds_category_code']=$d['code']??'';$rows[]=$tx;}
  $per=max(1,min(500,(int)($f['per_page']??100)));$current=max(1,(int)($f['page']??1));$total=count($rows);return ['data'=>array_slice($rows,($current-1)*$per,$per),'total'=>$total,'page'=>$current,'per_page'=>$per,'pages'=>(int)ceil($total/max(1,$per))];
 }
 public static function fetchBankTransactionForClassification(PDO $pdo,int $id):?array{
  $page=1;do{$batch=BankFinanceService::getTransactions($pdo,null,$page,500);foreach($batch['data'] as $tx)if((int)$tx['id']===$id)return $tx;$page++;}while(($page-1)*500<(int)$batch['total']);return null;
 }
}
