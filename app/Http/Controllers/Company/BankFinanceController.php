<?php
namespace App\Http\Controllers\Company;
use App\Core\Database;
final class BankFinanceController{
 public function __construct(private readonly array $config,private readonly Database $db){}
 private function action(string $name):void{$config=$this->config;$db=$this->db;require base_path('app/Http/Controllers/Company/BankFinanceActions/'.$name.'.php');}
 public function index():void{$this->action('index');}
 public function import():void{$this->action('import');}
 public function refreshFromMail():void{$this->action('refreshFromMail');}
 public function settings():void{$this->action('settings');}
 public function bankStatementSettings():void{$this->action('bankStatementSettings');}
 public function deleteImport():void{$this->action('deleteImport');}
 public function reconciliationJson():void{$this->action('reconciliationJson');}
 public function classifyForm(int $id):void{$bankTransactionId=$id;$this->action('classifyForm');}
 public function classifySubmit(int $id):void{$bankTransactionId=$id;$this->action('classifySubmit');}
}
