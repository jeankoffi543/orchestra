<?php

namespace Kjos\Orchestra\Commands;

use Illuminate\Console\Command;
use Kjos\Orchestra\Services\Deployer;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'orchestra:create:deployer')]
class CreateDeployerCommand extends Command
{
    /**
     * Console command name
     *
     * @var string
     */
    protected $name = 'orchestra:create:deployer';

    /**
     * Console command description
     *
     * @var string
     */
    protected $description = 'Create a deployer.';

    /**
     * Console command signature
     *
     * @var string
     */
    protected $signature = 'orchestra:create:deployer {name?}';

    /**
     * Handle the command.
     *
     * This command asks the user to confirm the creation of a special 'deployer' system user.
     * This user is used to automate the management of VirtualHosts Apache from the Laravel application.
     * It allows, without password, to execute the following commands securely:
     *   - Create/delete VirtualHosts (a2ensite, a2dissite)
     *   - Create directories in /var/www/html
     *   - Create symbolic links
     *   - Copy configuration files to /etc/apache2/sites-available
     * The operation also configures the necessary access rights to allow the web application to delegate these actions to 'deployer'.
     * If the user confirms, the command creates the user and configures the necessary rights.
     * If the user cancels, the command does nothing.
     *
     * @return int
     */
    public function handle(): int
    {
        try {
            if (! app()->runningInConsole()) {
                return Command::SUCCESS;
            }
            $user = \get_current_user();

            $deployer = new Deployer();
            $this->info("🛠️ Voulez-vous activer le système de cron pour l\'utilisateur courant: {$user} ?\n");

            $this->line('Ce compte spécial sera utilisé pour automatiser la gestion des VirtualHosts Apache depuis l’application Laravel.');
            $this->line('Il permettra, sans mot de passe, d’exécuter les commandes suivantes en toute sécurité :');
            $this->line('  • Créer/supprimer des VirtualHosts (a2ensite, a2dissite)');
            $this->line('  • Créer des répertoires dans /var/www/html');
            $this->line('  • Créer des liens symboliques');
            $this->line('  • Copier les fichiers de configuration dans /etc/apache2/sites-available');
            $this->line("\nCette opération configure également les droits d’accès nécessaires pour permettre à l'application web de déléguer ces actions à '{$user}'.\n");

            if ($this->confirm('Souhaitez-vous procéder à la création de cet utilisateur ?', true)) {
                $deployer->init($this->argument('name'));
                $this->info("✅ Utilisateur '{$user}' configuré avec succès.");
            } else {
                $this->warn("❌ Opération annulée. L'utilisateur '{$user}' n'a pas été créé.");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Erreur : ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
