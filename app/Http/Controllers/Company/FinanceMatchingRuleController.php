<?php
namespace App\Http\Controllers\Company;
use App\Core\Database;
final class FinanceMatchingRuleController{
 public function __construct(private readonly array $config,private readonly Database $db){}
 private function action(string $name):void{$config=$this->config;$db=$this->db;require base_path('app/Http/Controllers/Company/FinanceMatchingRuleActions/'.$name.'.php');}
 public function index():void{$this->action('index');}public function createForm():void{$this->action('create_form');}public function createSubmit():void{$this->action('create_submit');}public function editForm():void{$this->action('edit_form');}public function editSubmit():void{$this->action('edit_submit');}public function toggle():void{$this->action('toggle');}public function reorder():void{$this->action('reorder');}public function preview():void{$this->action('preview');}public function testOnTransaction():void{$this->action('test_on_transaction');}public function remove():void{$this->action('archive');}public function archive():void{$this->action('archive');}public function cfuSave():void{$this->action('cfu_save');}public function cfuToggle():void{$this->action('cfu_status');}
}
