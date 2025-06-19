<?php

namespace Kjos\Orchestra\Services;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class Deployer extends Shell
{
    protected string $scriptPath;
    protected string $cronLogPath;
    protected string $basePath;
    protected string $currentsystUser;
    protected string $mv;
    protected string $rm;
    protected string $a2ensite;
    protected string $a2dissite;
    protected string $systemctl;
    protected string $ln;
    protected string $bash;
    protected string $mkdir;

    public function __construct()
    {
        parent::__construct();
        $this->scriptPath = base_path('/vendor/bin/orchestra-vhost-manager');


        $this->cronLogPath = $this->getCronLogPath();

        $this->basePath        = \rtrim(base_path(), '/');
        $this->currentsystUser = \get_current_user();

        $this->mv        = \trim(\shell_exec('which mv'));
        $this->rm        = \trim(\shell_exec('which rm'));
        $this->a2ensite  = \trim(\shell_exec('which a2ensite'));
        $this->systemctl = \trim(\shell_exec('which systemctl'));
        $this->ln        = \trim(\shell_exec('which ln'));
        $this->a2dissite = \trim(\shell_exec('which a2dissite'));
        $this->mkdir     = \trim(\shell_exec('which mkdir'));
        $this->bash      = \trim(\shell_exec('which bash'));
    }

    public function init(): void
    {
        try {
            $this->createFile('/etc/sudoers.d/deployer');
            $this->userCommand();
            $this->addScript();

            if (config('cache.default') === 'database') {
                Artisan::call('cache:table');
                Artisan::call('migrate');
            }

            $this->crontab();
        } catch (\Exception $e) {
            $this->reset();
        }
    }

    public function reset(): void
    {
        $this->deleteFile('/etc/sudoers.d/deployer');
        $this->crontab(true);
        $this->removeScript();
    }

    public function crontab(bool $remove = false): void
    {
        $cronCmd = "/usr/bin/php {$this->basePath}/artisan schedule:run";
        // $cronLine = "* * * * * $cronCmd >> {$this->cronLogPath} 2>&1";
        $cronLine = "* * * * * $cronCmd";

        // Lire la crontab actuelle
        $currentCrontab = \shell_exec('crontab -l 2>/dev/null') ?? '';

        if ($remove) {
            $newCrontab = collect(\explode("\n", $currentCrontab))
               ->reject(fn ($line) => \str_contains($line, $cronCmd))
               ->implode("\n");

            \file_put_contents('/tmp/mycron', \trim($newCrontab) . PHP_EOL);
            \exec('crontab /tmp/mycron');
            \unlink('/tmp/mycron');

            return;
        }

        if (!\str_contains($currentCrontab, $cronCmd)) {
            $newCrontab = $currentCrontab . PHP_EOL . $cronLine . PHP_EOL;
            \file_put_contents('/tmp/mycron', \trim($newCrontab) . PHP_EOL);
            \exec('crontab /tmp/mycron');
            \unlink('/tmp/mycron');
        }
    }

    public function removeScript(): void
    {
        removeFileSecurely($this->scriptPath);
        removeFileSecurely($this->cronLogPath);
    }

    public function addScript(): void
    {
        \file_put_contents($this->scriptPath, $this->generateDeployerScript());
        $this->addCode("sudo chmod +x {$this->scriptPath}")
           ->addCode("sudo chown {$this->currentsystUser}:{$this->currentsystUser} {$this->scriptPath}")
           ->addCode("sudo chmod 750 {$this->scriptPath}")
           ->addCode("sudo touch {$this->cronLogPath}")
           ->addCode("sudo chown {$this->currentsystUser}:{$this->currentsystUser} {$this->cronLogPath}")
           ->addCode("sudo chmod 750 {$this->cronLogPath}")
           ->execute();
    }

    public function userCommand(): void
    {
        $this->addCode("echo '{$this->currentsystUser} ALL=(ALL) NOPASSWD: {$this->mv}, {$this->rm}, {$this->a2ensite}, {$this->a2dissite}, {$this->systemctl}, {$this->ln}, {$this->mkdir}' | sudo tee -a /etc/sudoers.d/deployer")
           ->execute();
    }

    public function createFile(string $path): void
    {
        if ($this->fileExists($path)) {
            $this->deleteFile($path);
        }
        $exitCode = $this->addCode("sudo touch $path")->execute();
        if (!$exitCode) {
            throw new \Exception('Unable to create deployer file', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function deleteFile(string $path): bool
    {
        $exitCode = true;
        if ($this->fileExists($path)) {
            $exitCode = $this->addCode("sudo rm $path")->execute();
        }

        return $exitCode;
    }

    public function fileExists(string $path): bool
    {
        return $this->addCode("sudo test -f $path")->execute();
    }

    /**
     * Génère le script bash qui sera utilisé pour déployer/réinitialiser
     * un VirtualHost Apache2.
     *
     * @return string Le code du script bash
     */
    public function generateDeployerScript(): string
    {
        $script = <<<'EOT'
         #!/usr/bin/bash
         
         # Valeurs par défaut
         DOMAIN=""
         NAME=""
         CONF_PATH=""
         LOG_DIR=""
         BASE_PATH=""

         show_help() {
            echo "Usage: $0 [deploy|remove] --domain DOMAIN --conf FILE --name NAME --base-path BASE_PATH"
            echo
            echo "Actions:"
            echo "  deploy       Déployer un VirtualHost Apache2"
            echo "  remove       Supprimer un VirtualHost Apache2"
            echo
            echo "Options:"
            echo "  --domain     Domaine du site (obligatoire)"
            echo "  --conf       Chemin vers le fichier de configuration (requis pour deploy)"
            echo "  --name       Nom du tenant (requis pour deploy)"
            echo "  --base-path  Chemin de base du site  (requis pour deploy)"
            echo
            exit 1
         }

         # Lire l'action principale
         if [[ "$1" == "deploy" || "$1" == "remove" ]]; then
            ACTION="$1"
            shift
         else
            show_help
         fi

         # Analyse des arguments
         while [[ "$#" -gt 0 ]]; do
            case "$1" in
               --domain)
                     DOMAIN="$2"
                     shift 2
                     ;;
               --conf)
                     CONF_PATH="$2"
                     shift 2
                     ;;
                  --name)
                     NAME="$2"
                     shift 2
                     ;;
                  --base-path)
                     BASE_PATH="$2"
                     shift 2
                     ;;  
                  --cron-log)
                     CRON_LOG="$2"
                     shift 2
                     ;;  

                     *)
                     echo "Option inconnue : $1"
                     show_help
                     exit 1
                     ;;
            esac
         done

         # Validation
         #if [[ -z "$DOMAIN" || -z "$CONF_PATH" -z "$NAME"  ]]; then
         #    echo "Usage: $0 --domain example.com --conf /tmp/example.com.conf --name tenant"
         #    exit 1
         #fi

         if [ -f "$CRON_LOG" ]; then
            exec >> "$CRON_LOG" 2>&1
         fi
         # Exécuter les actions
         case "$ACTION" in
            deploy)
               if [[ -z "$CONF_PATH" || -z "$NAME" || -z "$DOMAIN" || -z "$BASE_PATH" ]]; then
                     echo "Erreur : --conf, --name, --base-path et --domain sont requis pour deploy."
                     exit 1
               fi

                  echo "[$(date)] Début du déploiement de: $DOMAIN"

                  LOG_DIR="/var/log/apache2/$DOMAIN"
                  DEST_CONF="/etc/apache2/sites-available/$(basename "$CONF_PATH")"        
                  SITE_LINK="/var/www/html/$DOMAIN"
                  TENANT_BASE="$BASE_PATH/site/$NAME"

                  echo "➤ Déploiement du site $DOMAIN"
 
                  sudo mkdir -p "$SITE_LINK"

                  # Création du fichier, dossier et activation
                  if [ -f "$CONF_PATH" ]; then
                    sudo mv "$CONF_PATH" "$DEST_CONF"
                  else
                     echo "Fichier de configuration non trouvé : $CONF_PATH"
                     exit 1
                  fi

                  
                 sudo mkdir -p "$LOG_DIR"

                  #ajouter lien symbolique
                  if [ -L "$SITE_LINK/public_html" ] || [ -d "$SITE_LINK/public_html" ] || [ -e "$SITE_LINK/public_html" ] ; then
                     rm -rf "$SITE_LINK/public_html"
                  fi

                  sudo ln -s "$TENANT_BASE/public_html" "$SITE_LINK/public_html"

                  sudo a2ensite $(basename "$CONF_PATH")
                  sudo systemctl reload apache2

               echo "✅ Site $DOMAIN activé avec succès."

               echo "Fin ➤"

               ;;

            remove)

            if [[ -z "$CONF_PATH" || -z "$DOMAIN" ]]; then
                     echo "Erreur : --conf et --domain sont requis pour deploy."
                     exit 1
               fi

               echo "[$(date)] Début de suppression de: $DOMAIN"

               LOG_DIR="/var/log/apache2/$DOMAIN"
               DEST_CONF="/etc/apache2/sites-available/$CONF_PATH"        
               SITE_LINK="/var/www/html/$DOMAIN"

               #suppression du site
               sudo rm -rf "$SITE_LINK"

               echo "➤ Suppression du site $DOMAIN"
               sudo a2dissite "$CONF_PATH"
               sudo rm -rf "$DEST_CONF"
               sudo rm -rf "$LOG_DIR"

               sudo systemctl reload apache2
               echo "✅ Site $DOMAIN supprimé avec succès."

               echo "Fin>>"
               ;;

            *)
               echo "Action invalide"
               show_help
               ;;
         esac         
      EOT;

        return $script;
    }

    public function getCronLogPath(): ?string
    {
        // Valeur par défaut si config est vide
        $logFile = config('orchestra.cron_log_path') ?? 'orchestra-deployer.log';

        // Construction du chemin complet
        $cronLog = storage_path("logs/{$logFile}");

        // Vérifie que c'est bien un fichier (et non un dossier)
        if (!File::exists($cronLog)) {
            // Vérifie que le dossier "logs" existe
            if (!File::isDirectory(\dirname($cronLog))) {
                File::makeDirectory(\dirname($cronLog), 0755, true);
            }

            File::put($cronLog, '');
        }

        return $cronLog;
    }
}
