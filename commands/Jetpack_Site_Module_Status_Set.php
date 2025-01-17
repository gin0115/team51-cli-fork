<?php

namespace WPCOMSpecialProjects\CLI\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use WPCOMSpecialProjects\CLI\Helper\AutocompleteTrait;

/**
 * Set the on/off status for a given Jetpack module on a given site.
 */
#[AsCommand( name: 'jetpack:set-site-module-status', aliases: array( 'jetpack:toggle-site-module' ) )]
final class Jetpack_Site_Module_Status_Set extends Command {
	use AutocompleteTrait;

	// region FIELDS AND CONSTANTS

	/**
	 * Domain or WPCOM ID of the site to fetch the information for.
	 *
	 * @var \stdClass|null
	 */
	private ?\stdClass $site = null;

	/**
	 * The module to set the status for.
	 *
	 * @var string|null
	 */
	private ?string $module = null;

	/**
	 * The status to set the module to.
	 *
	 * @var string|null
	 */
	protected ?string $status = null;

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 */
	protected function configure(): void {
		$this->setDescription( 'Sets the status of Jetpack modules on a given site.' )
			->setHelp( 'Use this command to enable/disable a given Jetpack module on a given site. This command requires that the given site has an active Jetpack connection to WPCOM.' );

		$this->addArgument( 'site', InputArgument::REQUIRED, 'Domain or WPCOM ID of the site to set the module status on.' )
			->addArgument( 'module', InputArgument::REQUIRED, 'The module to set the status for.' )
			->addArgument( 'status', InputArgument::OPTIONAL, 'The status to set the module to. Must be one of \'on\' or \'off\'. By default, \'on\'.' );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function initialize( InputInterface $input, OutputInterface $output ): void {
		$this->site = get_wpcom_site_input( $input, fn() => $this->prompt_site_input( $input, $output ) );
		$input->setArgument( 'site', $this->site );

		$this->module = get_enum_input( $input, 'module', array_keys( get_jetpack_site_modules( $this->site->ID ) ?? array() ), fn() => $this->prompt_module_input( $input, $output ) );
		$input->setArgument( 'module', $this->module );

		$this->status = get_enum_input( $input, 'status', array( 'on', 'off' ), fn() => $this->prompt_status_input( $input, $output ), 'on' );
		$input->setArgument( 'status', $this->status );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function interact( InputInterface $input, OutputInterface $output ): void {
		$question = new ConfirmationQuestion( "<question>Are you sure you want to set the status of the Jetpack module $this->module to $this->status on {$this->site->name} (ID {$this->site->ID}, URL {$this->site->URL})? [y/N]</question> ", false );
		if ( true !== $this->getHelper( 'question' )->ask( $input, $output, $question ) ) {
			$output->writeln( '<comment>Command aborted by user.</comment>' );
			exit( 2 );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute( InputInterface $input, OutputInterface $output ): int {
		$output->writeln( "<fg=magenta;options=bold>Setting the status of the Jetpack module $this->module to $this->status on {$this->site->name} (ID {$this->site->ID}, URL {$this->site->URL}).</>" );

		$result = update_jetpack_site_modules_settings( $this->site->ID, array( $this->module => 'on' === $this->status ) );
		if ( true !== $result ) {
			$output->writeln( '<error>Failed to update the module status.</error>' );
			return Command::FAILURE;
		}

		$output->writeln( '<fg=green;options=bold>Module status updated successfully.</>' );
		return Command::SUCCESS;
	}

	// endregion

	// region HELPERS

	/**
	 * Prompts the user for a site.
	 *
	 * @param   InputInterface  $input  The input object.
	 * @param   OutputInterface $output The output object.
	 *
	 * @return  string|null
	 */
	private function prompt_site_input( InputInterface $input, OutputInterface $output ): ?string {
		$question = new Question( '<question>Enter the domain or WPCOM site ID to set the module status on:</question> ' );
		if ( ! $input->getOption( 'no-autocomplete' ) ) {
			$question->setAutocompleterValues( array_column( get_wpcom_jetpack_sites() ?? array(), 'domain' ) );
		}

		return $this->getHelper( 'question' )->ask( $input, $output, $question );
	}

	/**
	 * Prompts the user for a module.
	 *
	 * @param   InputInterface  $input  The input object.
	 * @param   OutputInterface $output The output object.
	 *
	 * @return  string|null
	 */
	private function prompt_module_input( InputInterface $input, OutputInterface $output ): ?string {
		$question = new Question( '<question>Enter the module to set the status for:</question> ' );
		if ( ! $input->getOption( 'no-autocomplete' ) ) {
			$question->setAutocompleterValues( array_keys( get_jetpack_site_modules( $this->site->ID ) ?? array() ) );
		}

		return $this->getHelper( 'question' )->ask( $input, $output, $question );
	}

	/**
	 * Prompts the user for a status.
	 *
	 * @param   InputInterface  $input  The input object.
	 * @param   OutputInterface $output The output object.
	 *
	 * @return  string|null
	 */
	private function prompt_status_input( InputInterface $input, OutputInterface $output ): ?string {
		$question = new Question( '<question>Enter the status to set the module to:</question> ' );
		if ( ! $input->getOption( 'no-autocomplete' ) ) {
			$question->setAutocompleterValues( array( 'on', 'off' ) );
		}

		return $this->getHelper( 'question' )->ask( $input, $output, $question );
	}

	// endregion
}
