@ECHO OFF
 
rem Include GIT on the path
SET PATH=%PATH%;D:\Git\cmd
 
SET composerScript=f:\composer\composer.phar
php "%composerScript%" %*