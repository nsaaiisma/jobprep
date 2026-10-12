<?php
// Di app/Models/User.php, ubah $fillable menjadi:
protected $fillable = ['name', 'email', 'password', 'google_id', 'avatar'];
// (casts 'password' => 'hashed' sudah bawaan Laravel 11)
