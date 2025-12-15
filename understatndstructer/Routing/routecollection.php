<?php

class RouterCollection {
  protected $stack;
  public function __construct()
  {
   $this->stack=[];
  }
  public function add($route){
    array_push($this->stack,$route);
  }
}


?>