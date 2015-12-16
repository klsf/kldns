<?php
session_start();
setCookie('kldns_sid',null,-1,'/');
exit("<script language='javascript'>alert('已成功安全退出！返回网站首页！');window.location.href='/';</script>");