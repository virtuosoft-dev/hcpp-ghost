# hestiacp-ghost
A plugin for Hestia Control Panel (via hestiacp-pluginable) that enables hosting a Ghost instance. With this plugin installed, a new Quick Installer option will appear. User accounts can host their own Ghost instance either in the root domain or as a subfolder installation. For instance, it is possible to run Ghost in the root domain while having NodeBB installed on the same domain in a subfolder (i.e. https://example.com/nodebb-forum) or vice versa.

&nbsp;
> :warning: !!! Note: this repo is in progress; when completed, a release will appear in the release tab.

## Installation
HestiaCP-Ghost requires an Ubuntu based installation of [Hestia Control Panel](https://hestiacp.com) in addition to an installation of [HestiaCP-Pluginable](https://github.com/steveorevo/hestiacp-pluginable) *and* [HesitaCP-NodeApp](https://github.com/steveorevo/hestiacp-nodeapp) to function; please ensure that you have first installed both Pluginable and NodeApp on your Hestia Control Panel before proceeding. Switch to a root user and simply clone this project to the `/usr/local/hestia/plugins` folder. It should appear as a subfolder with the name `ghost`, i.e. `/usr/local/hestia/plugins/ghost`.

First, switch to root user:
```
sudo -s
```

Then simply clone the repo to your plugins folder, with the name `ghost`:

```
cd /usr/local/hestia/plugins
git clone https://github.com/steveorevo/hestiacp-ghost ghost
```

Note: It is important that the destination plugin folder name is `ghost`.

Be sure to logout and login again to your Hestia Control Panel as the admin user or, as admin, visit Server (gear icon) -> Configure -> Plugins -> Save; the plugin will immediately start installing NodeJS and Ghost depedencies in the background. A notification will appear under the admin user account indicating *"Ghost plugin has finished installing"* when complete. This may take awhile before the options appear in Hestia. You can force manual installation via root level SSH:

```
sudo -s
cd /usr/local/hestia/plugins/ghost
./install
```
