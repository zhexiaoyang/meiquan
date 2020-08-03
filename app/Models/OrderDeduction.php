<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class OrderDeduction extends Model
{
    protected $fillable = ['order_id', 'money', 'ps'];
}