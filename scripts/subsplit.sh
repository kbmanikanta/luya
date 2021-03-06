#!/bin/bash
# apt-get install svn
# wget https://github.com/dflydev/git-subsplit/archive/master.zip

REPO="git@github.com:luyadev/luya"
BASE="git@github.com:luyadev"

if [ "$1" = "init" ]; then
	git subsplit init $REPO
else
	git subsplit update
fi

git subsplit publish "
    core:$BASE/luya-core.git
    envs/kickstarter:$BASE/luya-kickstarter.git
    modules/admin:$BASE/luya-module-admin.git
    modules/cms:$BASE/luya-module-cms.git
    modules/cmsadmin:$BASE/luya-module-cmsadmin.git
    modules/news:$BASE/luya-module-news.git
    modules/newsadmin:$BASE/luya-module-newsadmin.git
    modules/account:$BASE/luya-module-account.git
    modules/accountadmin:$BASE/luya-module-accountadmin.git
    modules/errorapi:$BASE/luya-module-errorapi.git
    modules/exporter:$BASE/luya-module-exporter.git
    modules/gallery:$BASE/luya-module-gallery.git
    modules/galleryadmin:$BASE/luya-module-galleryadmin.git
    modules/crawler:$BASE/luya-module-crawler.git
    modules/crawleradmin:$BASE/luya-module-crawleradmin.git
    modules/styleguide:$BASE/luya-module-styleguide.git
    modules/frontendgroup:$BASE/luya-module-frontendgroup.git
    modules/remoteadmin:$BASE/luya-module-remoteadmin.git
" --heads=master -q
