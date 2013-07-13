<?php

defined('IN_EZRPG') or exit;
require_once( LIB_DIR . '/pclzip.lib.php' );
/*
  Class: Admin_Plugins
  Admin page for managing plugins and modules
 */

class Admin_Plugins extends Base_Module
{
    /*
      Function: start
      Displays the list of modules/plugins.
     */

    public function start()
    {

        if ( isset($_GET['act']) )
        {
            switch ( $_GET['act'] )
            {
                case 'view':
                    $this->view_modules($_GET['id']); //TODO:View Modules
                    break;
                case 'upload':
                    $this->upload_modules(); //Completed:Upload Modules
                    break;
                case 'remove':
                    $this->remove_modules($_GET['id']); //TODO:Remove Modules
                    break;
                case 'list':
                    $this->list_modules(); //Completed:Lists Modules
                    break;
                case 'enable':
					$this->enable_modules($_GET['id']);
                    break; //TODO:activate plugins after they've been installed.
                case 'disable':
					$this->disable_modules($_GET['id']);
                    break; //TODO:deactivate plugins but not remove them.
            }
        }
        else
        {
            $this->list_modules();
        }
    }

    private function list_modules()
    {
        $query = $this->db->execute('select * from <ezrpg>plugins JOIN <ezrpg>plugins_meta ON <ezrpg>plugins_meta.plug_id = <ezrpg>plugins.id');
        $plugins = Array( );
        while ( $m = $this->db->fetch($query) )
        {
            $plugins[] = $m;
        }
        $this->tpl->assign("plugins", $plugins);
        $this->loadView('plugins.tpl');
    }
	
	private function enable_modules($id)
    {
        $query = $this->db->execute('UPDATE <ezrpg>plugins SET <ezrpg>plugins.active = 1 WHERE <ezrpg>plugins.id = '.$id.' OR <ezrpg>plugins.pid = '.$id);
        $this->db->fetch($query);
		$query2 = $this->db->execute('UPDATE <ezrpg>menu SET <ezrpg>menu.active = 1 WHERE <ezrpg>menu.module_id = '.$id);
        $this->db->fetch($query2);
		killMenuCache();
        $this->setMessage("Module enabled!");
		killModuleCache();
        header('Location: index.php?mod=Plugins');
		exit;
    }
	private function disable_modules($id)
    {
        $query = $this->db->execute('UPDATE <ezrpg>plugins SET <ezrpg>plugins.active = 0 WHERE <ezrpg>plugins.id = '.$id.' OR <ezrpg>plugins.pid = '.$id);
        $this->db->fetch($query);
        $query2 = $this->db->execute('UPDATE <ezrpg>menu SET <ezrpg>menu.active = 0 WHERE <ezrpg>menu.module_id = '.$id);
        $this->db->fetch($query2);
		killMenuCache();
		$this->setMessage("Module disabled!");
		killModuleCache();
        header('Location: index.php?mod=Plugins');
		exit;
    }

    private function upload_modules()
    {
        if ( isset($_FILES['file']) )
        {
            if ( $_FILES["file"]["error"] > 0 )
            {
                $this->tpl->assign("MSG", "Error: " . $_FILES["file"]["error"]);
                $this->loadView('upload_plugins.tpl');
            }
            else
            {
                if ( $_FILES["file"]["type"] == "application/x-zip-compressed" )
                {
                    $zip = new PclZip($_FILES["file"]["tmp_name"]);
                    $ziptempdir = substr(uniqid('', true), -5);
                    $dir = "Plugins/temp/" . $ziptempdir;
                    $results = "";
                    if ( $zip->extract(PCLZIP_OPT_PATH, $dir) == 0 )
                    {
                        $results .= "Error : " . $zip->errorInfo(true);
                    }
                    $results .= "Plugin extracted to " . $dir . "<br />";
                    if ( file_exists($dir . "/plugin.xml") )
                    {
                        $results .= "this is a proper module/plugin. <br />";
                        $plug = simplexml_load_file($dir . "/" . "plugin.xml");
                        $p_d['title'] = (string) $plug->Plugin->Name;
                        $p_d['type'] = (string) $plug->Plugin->Type;
                        $p_d['filename'] = (string) $plug->Plugin->File;
                        $p_m['version'] = (string) $plug->Plugin->Version;
                        $p_m['author'] = (string) $plug->Plugin->Author;
                        $p_m['description'] = (string) $plug->Plugin->Description;
                        $p_m['url'] = (string) $plug->Plugin->AccessURL;
                        $p_m['uninstall'] = (string) $plug->Plugin->UninstallArg;
                        $p_m['plug_id'] = $this->db->insert('<ezrpg>plugins', $p_d);
                        $this->db->insert('<ezrpg>plugins_meta', $p_m);
                        if ( !empty($plug->Plugin->Hook) )
                        {
                            if ( !empty($plug->Plugin->Hook->HookFile) )
                            {
                                foreach ( $plug->Plugin->Hook->HookFile as $hooks )
                                {
                                    $hook['pid'] = $p_m['pid'];
                                    $hook['filename'] = (string) $hooks->HookFileName;
                                    $hook['title'] = (string) $hooks->HookTitle;
                                    $hook['type'] = 'hook';
                                    $this->db->insert('<ezrpg>plugins', $hook);
                                }
                            }
                        }
                        if ( !empty($plug->Plugin->Lib) )
                        {
                            if ( !empty($plug->Plugin->Lib->LibFile) )
                            {
                                foreach ( $plug->Plugin->Lib->LibFile as $libs )
                                {
                                    $lib['pid'] = $p_m['pid'];
                                    $lib['filename'] = (string) $libs->HookFileName;
                                    $lib['title'] = (string) $libs->HookTitle;
                                    $lib['type'] = 'library';
                                    $this->db->insert('<ezrpg>plugins', $lib);
                                }
                            }
                        }
                        if ( !empty($plug->Plugin->Theme) )
                        {
                            if ( !empty($plug->Plugin->Theme->ThemeFolder) )
                            {
                                foreach ( $plug->Plugin->Theme->ThemeFolder as $theme )
                                {
                                    $theme_m['pid'] = $p_m['pid'];
                                    $theme_m['filename'] = (string) $theme->ThemeFolder;
                                    $theme_m['title'] = (string) $theme->ThemeTitle;
                                    $theme_m['type'] = 'templates';
                                    $this->db->insert('<ezrpg>plugins', $theme_m);
                                }
                            }
                        }
                        $results .= "installed db data <br />";
                        if ( file_exists($dir . '/modules') )
                            $this->re_copy($dir . '/modules/', MOD_DIR);
                        if ( file_exists($dir . '/lib') )
                            $this->re_copy($dir . '/lib/', LIB_DIR);
                        if ( file_exists($dir . '/hooks') )
                            $this->re_copy($dir . '/hooks/', HOOKS_DIR);
                        if ( file_exists($dir . '/admin') )
                            $this->re_copy($dir . '/admin/', ADMIN_DIR);
                        if ( file_exists($dir . '/templates') )
                            $this->re_copy($dir . '/templates/', THEME_DIR);

                        $this->rrmdir($dir);
                        $results .= "You have successfully uploaded a plugin via the manager! <br />";
                        if ( !empty($plug->Plugin->AccessURL) )
                        {
                            $install_url = str_replace('SITE_URL/', $this->settings->get_settings('general')['site_url'], $p_m['url']);
                            if ( !empty($plug->Plugin->InstallArg) )
                            {
                                $install_url .= (string) $plug->Plugin->InstallArg;
                                $results .= "<a href='" . $install_url . "'><input name='install' type='submit' class='button' value='Install Plugin' /></a>";
                            }
                            else
                            {
                                $query = $this->db->execute('UPDATE <ezrpg>plugins SET installed = 1, active = 1 WHERE id = ' . $p_m['pid'] . ' OR pid = ' . $p_m['pid']);
                                $this->db->execute( $query );
                                $results .= "<a href='" . $install_url . "'><input name='install' type='submit' class='button' value='Go To Plugin' /></a>";
                            }
                        }
                        $results .= "<a href='index.php?mod=Plugins'><input name='back' type='submit' class='button' value='Back to manager' /></a>";
                    }
                    else
                    {
                        $results .= $dir . "/" . pathinfo($_FILES['file']['name'], PATHINFO_FILENAME) . ".xml was not found <br />";
                        $results .= "This is not a valid Plugin <br />";
                        $this->rrmdir($dir);
                        $results .= "All temporary files have been deleted. <br />";
                        $results .= "<a href='index.php?mod=Plugins'><input name='login' type='submit' class='button' value='Back To Manager' /></a>";
                    }
                }
                else
                {
                    $results = "Uploaded Unsupported filetype. Only upload .zips at this time.<br>";
                    $results .= "<a href='index.php?mod=Plugins'><input name='login' type='submit' class='button' value='Back To Manager' /></a>";
                }
                $this->tpl->assign("RESULTS", $results);
                $this->loadView('plugin_results.tpl');
            }
        }
        else
        {
            $this->loadView('upload_plugins.tpl');
        }
    }

    private function remove_modules($id = 0)
    {
        if ( $id == 0 )
        {
            $this->list_modules();
            break;
        }
        $query = $this->db->execute('SELECT uninstall FROM <ezrpg>plugins_meta WHERE plug_id=' . $id);
        $result = $this->db->fetch($query);
        $query_mod = $this->db->execute('SELECT * FROM <ezrpg>plugins WHERE pid=' . $id);
		$result = $this->db->fetchAll($query_mod);
		foreach ( $result as $file )
		{
			$this->rrmdir($file->filename);
		}
		$this->db->execute('DELETE FROM <ezrpg>plugins WHERE pid=' . $id . ' OR id=' . $id);
		$this->db->execute('DELETE FROM <ezrpg>plugins_meta WHERE plug_id=' . $id);
        if ( $result->uninstall )
		{
            $url = $settings->get_settings('general')['site_url'];
			$this->setMessage('Module has been removed from DB. Please finish the uninstall process');
            header('Location: ' . $url . $result->uninstall);
            exit;
        }
    }

    private function view_modules($id = 0)
    {
        if ( $id == 0 )
        {
            $this->list_modules();
            break;
        }
    }

    private function rrmdir($dir)
    {
        if ( is_dir($dir) )
        {
            $objects = scandir($dir);
            foreach ( $objects as $object )
            {
                if ( $object != "." && $object != ".." )
                {
                    if ( filetype($dir . "/" . $object) == "dir" )
                        $this->rrmdir($dir . "/" . $object);
                    else
                        unlink($dir . "/" . $object);
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }

    private function re_copy($src, $dst)
    {
        $dir = opendir($src);
        @mkdir($dst);
        while ( false !== ( $file = readdir($dir) ) )
        {
            if ( ( $file != '.' ) && ( $file != '..' ) )
            {
                if ( is_dir($src . '/' . $file) )
                {
                    $this->re_copy($src . '/' . $file, $dst . '/' . $file);
                }
                else
                {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

}
