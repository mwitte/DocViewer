<?php
namespace TechDivision\DocViewer\Controller;

/*
 * This file is part of the TechDivision.DocViewer package.
 */
use TechDivision\DocViewer\Exceptions\PackageNotAccessableException;
use TechDivision\DocViewer\Exceptions\ParsingNotAllowedException;
use TechDivision\DocViewer\File\Parser;
use TechDivision\DocViewer\File\Tree;
use TechDivision\DocViewer\Util;

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Neos\Controller\Module\AbstractModuleController;

/**
 *
 * @Flow\Scope("singleton")
 */
class ModuleController extends AbstractModuleController
{

	/**
	 * @Flow\Inject
	 * @var \TYPO3\Flow\Package\PackageManagerInterface
	 */
	protected $packageManager;

	/**
	 * Files which are used as entry files
	 * @Flow\InjectConfiguration("packages")
	 * @var array
	 */
	protected $packagesConfiguration;

	/**
	 * @Flow\Inject
	 * @var \TechDivision\DocViewer\AccessManager
	 */
	protected $accessManager;

    /**
     * @return void
     */
    public function indexAction()
    {

		$packageGroups = array();

		foreach($this->packagesConfiguration['visibleTypes'] as $type) {
			$packageGroups[$type] = array();
		}
		foreach ($this->packageManager->getAvailablePackages() as $package) {

			if(!$this->accessManager->isPackageAccessable($package->getPackageKey())) {
				continue;
			}

			/** @var Package $package */
			$packagePath = substr($package->getPackagepath(), strlen(FLOW_PATH_PACKAGES));
			$packageGroup = substr($packagePath, 0, strpos($packagePath, '/'));

			if(!in_array($packageGroup, $this->packagesConfiguration['visibleTypes'])) {
				continue;
			}

			$tree = new Tree($packageGroup, $package->getPackageKey(), $this->controllerContext->getRequest()->getHttpRequest()->getBaseUri());

			if(!$tree->isDirectoryWithContent()) {
				continue;
			}

			$packageGroups[$packageGroup][$package->getPackageKey()] = array(
				'sanitizedPackageKey' => str_replace('.', '', $package->getPackageKey()),
				'version' => $package->getInstalledVersion(),
				'name' => $package->getComposerManifest('name'),
				'type' => $package->getComposerManifest('type'),
				'description' => $package->getPackageMetaData()->getDescription(),
				'metaData' => $package->getPackageMetaData(),
				'isActive' => $this->packageManager->isPackageActive($package->getPackageKey()),
				'isFrozen' => $this->packageManager->isPackageFrozen($package->getPackageKey()),
				'isProtected' => $package->isProtected(),
				'hasDoc' => $tree->isDirectoryWithContent()
			);

		}

		$this->view->assign('packageGroups', $packageGroups);

	}

	/**
	 * @param string $packageKey
	 * @param string $packageType
	 * @param string $filePath
	 */
	public function showAction($packageKey, $packageType, $filePath = null) {

		$baseUri = $this->controllerContext->getRequest()->getHttpRequest()->getBaseUri();

		if (!$this->accessManager->isPackageAccessable($packageKey)) {
			throw new PackageNotAccessableException("You are not allowed to access the package " . $packageKey);
		}

		$this->view->assign('packageKey', $packageKey);
		$this->view->assign('packageType', $packageType);

		$tree = new Tree($packageType, $packageKey, $baseUri);

		if(!$tree->isDirectoryWithContent()) {
			$this->addFlashMessage('No documention could be found');
		}
		$this->view->assign('node', $tree->getRootNode());

		if($filePath) {
			$file = $tree->findFileNodeByPath($filePath);
		}else {
			$file = $tree->findEntryFile();
		}

		if($file) {
			$parser = new Parser($baseUri);
			$this->view->assign('currentFile', $file);
			try {
				$documentContent = $parser->parseFile($file);
				$this->view->assign('doc', $documentContent);
			}catch (ParsingNotAllowedException $e) {
				$this->addFlashMessage($e->getMessage());
			}
		}
	}
}
