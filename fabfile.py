from fabric.api import env, local, run, sudo, task, require, puts, hide, put
from fabric.context_managers import cd, lcd, prefix, settings
from fabric.contrib.console import confirm
from fabric.contrib.files import exists, upload_template
import os, time

# no passwords stored here, all host authentication is in  ~/.ssh/config

env.roledefs = {
    'development': ['localhost'],
    'staging': ['deploy@alpha.bugvote.com:2277']
}

# local root path relative to the location of this fabric file
# this allows us to perform fab commands from anywhere in the tree
env.local_root_path = os.path.dirname(os.path.realpath(__file__))

@task
def alpha():
    env.app_name = 'alpha.bugvote.com'
    env.app_root = '/var/www/%s' % env.app_name
    env.app_logs = '%s/logs/' % env.app_root
    env.app_url = 'alpha.bugvote.com'
    env.app_repo = '%s/repo.git' % env.app_root
    env.app_tools = '%s/tools' % env.app_root
    env.app_tmp = '%s/tmp' % env.app_root
    env.app_storage = '%s/storage' % env.app_root
    env.app_builds = '%s/builds' % env.app_root
    env.app_current_build_symlink = '%s/system' % env.app_root
    env.composer_cache = '%s/composer.cache' % env.app_tmp

    env.local_nginx_conf = 'conf/nginx.conf'
    env.local_nginx_vhost_conf = 'conf/nginx-vhost.conf'
    env.local_php_fpm_conf = 'conf/php-fpm.conf'
    env.local_mysql_conf = 'conf/my.cnf'
    
    env.remote_nginx_conf_path = '/etc/nginx/nginx.conf'
    env.remote_nginx_vhost_conf_path = '/etc/nginx/sites-enabled/%s.conf' % env.app_name
    env.remote_php_fpm_conf_path = '/etc/php5/fpm/pool.d/%s.conf' % env.app_name
    env.remote_mysql_conf_path = '/etc/mysql/my.cnf'
    
    env.hosts = env.roledefs['staging']

@task
def configure():
    require('local_nginx_conf', 'local_php_fpm_conf', provided_by=[alpha])
    
    with lcd(env.local_root_path):

        # TODO: add confirmation
        #if os.path.exists(env.app_root):
        #    puts("app root path already exists, do you want to proceed?")
        #    return
        
        # oh my
        sudo('rm -rf %s' % env.app_root)
        
        # setup standalone deployment path
        # have to do it as root first, then drop down to regular 'deploy' user
        sudo('mkdir -p %s' % env.app_root)
        sudo('chown deploy:www-data %s' % env.app_root)
        
        run('mkdir -p %s' % env.app_logs)
        
        # install composer globally
        run('mkdir -p %s', env.composer_cache)
        run('mkdir -p %s' % env.app_tools)
        run('curl -sS https://getcomposer.org/installer | php -- --install-dir=%s --cache-dir=%s' % (env.app_tools, env.composer_cache))
    
        # init repo, we will push here before prior to deployment
        if not exists(env.app_repo):
            run('mkdir -p %s' % env.app_repo)
            with cd(env.app_repo):
                run('git init --bare')
        
        # chown the whole thing g+wr for www-data user
        sudo('chown deploy:www-data -hR %s' % env.app_root)
        
        # update nginx configurations
        upload_template(env.local_nginx_conf, env.remote_nginx_conf_path, env, use_sudo=True)
        upload_template(env.local_nginx_vhost_conf, env.remote_nginx_vhost_conf_path, env, use_sudo=True)
        upload_template(env.local_php_fpm_conf, env.remote_php_fpm_conf_path, env, use_sudo=True)
        upload_template(env.local_mysql_conf, env.remote_mysql_conf_path, env, use_sudo=True)

@task
def nginx_reload():
    sudo('nginx -s reload')

@task
def deploy_latest():
    with lcd(env.local_root_path):
        commit_id = local('git rev-parse HEAD', True)
        puts('forcing deploy of latest commit: %s' % commit_id)
        destroy(commit_id)
        deploy(commit_id)

# warning: this may leave a dangling symlink
@task
def destroy(commit_id):
    new_build = '%s/%s' % (env.app_builds, commit_id)
    if exists(new_build):
        run('rm -rf %s' % new_build)

@task
def deploy(commit_id, rebuild_dependencies=False):
    with lcd(env.local_root_path):

        # verify commit_id hash is in systems-src git's log
        with hide('output', 'running', 'warnings'), settings(warn_only=True):
            commit_test = local("git log %s -n 1 --oneline" % commit_id, True)
            if commit_test.startswith('fatal') or not commit_test.startswith(commit_id[:5]):
                puts("Canceling deploy; log does not contain commit: %s" % commit_id)
                exit()
        
        new_build = '%s/%s' % (env.app_builds, commit_id)
        puts('deploying to: %s' % new_build)

        run('mkdir -p %s' % new_build)
        run('mkdir -p %s' % env.composer_cache)
        run('git clone %s %s' % (env.app_repo, new_build))
        
        composer_path = '%s/composer.phar' % env.app_tools
        
        with cd(new_build):
            # adjust to the correct commit
            run('git reset --hard %s' % commit_id)
            
            # configure composer
            run('%s config cache-dir %s' % (composer_path, env.composer_cache))
            run('%s config preferred-install dist' % composer_path)
            
            php_vendors_cache = '%s/vendor.tar' % env.composer_cache
            
            if exists(php_vendors_cache) and not rebuild_dependencies:
                puts('reusing vendors cache')
                run('tar xf %s -C %s/' % (php_vendors_cache, new_build))
                #run('%s update --no-interaction --no-ansi --no-progress --no-dev --prefer-dist --profile' % composer_path)
            else:
                puts('rebuilding dependencies')
                run('%s install --no-interaction --no-ansi --no-progress --no-dev --prefer-dist --profile' % composer_path)

            new_build_conf_dir = '%s/app/' % new_build
            
            put('conf/dal.conf', new_build_conf_dir)
            put('conf/oauth.conf', new_build_conf_dir)

            # update paths (this should really be done using a template or external config)
            #sed('%s/config.php', 'static.dev.bugvote.com', 'static.alpha.dev.bugvote.com')
            
            # create symlink from build out to perm external asset storage
            run('ln -sfr %s storage' % env.app_storage)

            # adjust symlink to this new build
            run('rm %s' % env.app_current_build_symlink)
            run('ln -sfr %s %s' % (new_build, env.app_current_build_symlink))

            sudo('chown deploy:www-data -hR %s' % env.app_root)
            sudo('service php5-fpm restart')