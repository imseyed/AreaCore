<?php


use ORM\MySQL\MySQL;
use ORM\MySQL\NoColumn;
use ORM\MySQL\Unique;

class Blog
{
    use MySQL;
    
    public $ID;
    public $title;
    public $content;
    public $author;
    public ?int $publishTime;
    
    public $uri;
    
    #[NoColumn]
    public $comments;
    
}