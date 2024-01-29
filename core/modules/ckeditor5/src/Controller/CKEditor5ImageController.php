<?php

declare(strict_types=1);

namespace Drupal\ckeditor5\Controller;

use Drupal\Component\Utility\Bytes;
use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\Environment;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\Exception\FileException;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\editor\Entity\Editor;
use Drupal\file\Upload\FileUploadHandler;
use Drupal\file\Upload\FormUploadedFile;
use Drupal\file\Validation\FileValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Mime\MimeTypeGuesserInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Returns response for CKEditor 5 Simple image upload adapter.
 *
 * @internal
 *   Controller classes are internal.
 */
class CKEditor5ImageController extends ControllerBase {

  /**
   * The default allowed image extensions.
   */
  const DEFAULT_IMAGE_EXTENSIONS = 'gif png jpg jpeg';

  /**
   * The file system service.
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The lock.
   */
  protected LockBackendInterface $lock;

  /**
   * The file upload handler.
   */
  protected FileUploadHandler $fileUploadHandler;

  /**
   * Constructs a new CKEditor5ImageController.
   *
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file upload handler.
   * @param \Drupal\Core\Session\AccountInterface|\Drupal\file\Upload\FileUploadHandler $fileUploadHandler
   *   The currently authenticated user.
   * @param \Symfony\Component\Mime\MimeTypeGuesserInterface|\Drupal\Core\Lock\LockBackendInterface $mime_type_guesser
   *   The MIME type guesser.
   * @param \Drupal\Core\Lock\LockBackendInterface|null $lock
   *   The lock service.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface|null $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\file\Validation\FileValidatorInterface|null $file_validator
   *   The file validator.
   */
  public function __construct(FileSystemInterface $fileSystem, AccountInterface | FileUploadHandler $fileUploadHandler, MimeTypeGuesserInterface | LockBackendInterface $mime_type_guesser, LockBackendInterface $lock = NULL, EventDispatcherInterface $event_dispatcher = NULL, FileValidatorInterface $file_validator = NULL) {
    $this->fileSystem = $fileSystem;
    if ($fileUploadHandler instanceof AccountInterface) {
      @trigger_error('Calling ' . __METHOD__ . '() with the $current_user argument is deprecated in drupal:10.3.0 and is removed from drupal:11.0.0. See https://www.drupal.org/node/3388990', E_USER_DEPRECATED);
      $fileUploadHandler = \Drupal::service('file.upload_handler');
    }
    $this->fileUploadHandler = $fileUploadHandler;
    if ($mime_type_guesser instanceof MimeTypeGuesserInterface) {
      @trigger_error('Calling ' . __METHOD__ . '() with the $mime_type_guesser argument is deprecated in drupal:10.3.0 and is replaced with $lock from drupal:11.0.0. See https://www.drupal.org/node/3388990', E_USER_DEPRECATED);
      $mime_type_guesser = \Drupal::service('lock');
    }
    $this->lock = $mime_type_guesser;
    if ($lock) {
      @trigger_error('Calling ' . __METHOD__ . '() with the $lock argument in position 4 is deprecated in drupal:10.3.0 and is removed from drupal:11.0.0. See https://www.drupal.org/node/3388990', E_USER_DEPRECATED);
    }
    if ($event_dispatcher) {
      @trigger_error('Calling ' . __METHOD__ . '() with the $event_dispatcher argument is deprecated in drupal:10.3.0 and is removed from drupal:11.0.0. See https://www.drupal.org/node/3388990', E_USER_DEPRECATED);
    }
    if ($file_validator) {
      @trigger_error('Calling ' . __METHOD__ . '() with the $file_validator argument is deprecated in drupal:10.3.0 and is removed from drupal:11.0.0. See https://www.drupal.org/node/3388990', E_USER_DEPRECATED);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
      $container->get('file.upload_handler'),
      $container->get('lock')
    );
  }

  /**
   * Uploads and saves an image from a CKEditor 5 POST.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON object including the file URL.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Thrown when file system errors occur.
   * @throws \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException
   *   Thrown when validation errors occur.
   */
  public function upload(Request $request): Response {
    // Getting the UploadedFile directly from the request.
    /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $upload */
    $upload = $request->files->get('upload');
    $filename = $upload->getClientOriginalName();

    /** @var \Drupal\editor\EditorInterface $editor */
    $editor = $request->attributes->get('editor');
    $settings = $editor->getImageUploadSettings();
    $destination = $settings['scheme'] . '://' . $settings['directory'];

    // Check the destination file path is writable.
    if (!$this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY)) {
      throw new HttpException(500, 'Destination file path is not writable');
    }

    $validators = $this->getImageUploadValidators($settings);

    $file_uri = "{$destination}/{$filename}";
    $file_uri = $this->fileSystem->getDestinationFilename($file_uri, FileSystemInterface::EXISTS_RENAME);

    // Lock based on the prepared file URI.
    $lock_id = $this->generateLockIdFromFileUri($file_uri);

    if (!$this->lock->acquire($lock_id)) {
      throw new HttpException(503, sprintf('File "%s" is already locked for writing.', $file_uri), NULL, ['Retry-After' => 1]);
    }

    try {
      $uploadedFile = new FormUploadedFile($upload);
      $uploadResult = $this->fileUploadHandler->handleFileUpload($uploadedFile, $validators, $destination, FileSystemInterface::EXISTS_RENAME, FALSE);
      if ($uploadResult->hasViolations()) {
        throw new UnprocessableEntityHttpException((string) $uploadResult->getViolations());
      }
    }
    catch (FileException $e) {
      throw new HttpException(500, 'File could not be saved');
    }
    catch (LockAcquiringException $e) {
      throw new HttpException(503, sprintf('File "%s" is already locked for writing.', $upload->getClientOriginalName()), NULL, ['Retry-After' => 1]);
    }

    $this->lock->release($lock_id);

    $file = $uploadResult->getFile();
    return new JsonResponse([
      'url' => $file->createFileUrl(),
      'uuid' => $file->uuid(),
      'entity_type' => $file->getEntityTypeId(),
    ], 201);
  }

  /**
   * Gets the image upload validators.
   */
  protected function getImageUploadValidators(array $settings): array {
    $max_filesize = min(Bytes::toNumber($settings['max_size']), Environment::getUploadMaxSize());
    $max_dimensions = 0;
    if (!empty($settings['max_dimensions']['width']) || !empty($settings['max_dimensions']['height'])) {
      $max_dimensions = $settings['max_dimensions']['width'] . 'x' . $settings['max_dimensions']['height'];
    }
    return [
      'FileExtension' => [
        'extensions' => self::DEFAULT_IMAGE_EXTENSIONS,
      ],
      'FileSizeLimit' => [
        'fileLimit' => $max_filesize,
      ],
      'FileImageDimensions' => [
        'maxDimensions' => $max_dimensions,
      ],
    ];
  }

  /**
   * Access check based on whether image upload is enabled or not.
   *
   * @param \Drupal\editor\Entity\Editor $editor
   *   The text editor for which an image upload is occurring.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function imageUploadEnabledAccess(Editor $editor) {
    if ($editor->getEditor() !== 'ckeditor5') {
      return AccessResult::forbidden();
    }
    if ($editor->getImageUploadSettings()['status'] !== TRUE) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

  /**
   * Generates a lock ID based on the file URI.
   *
   * @param string $file_uri
   *   The file URI.
   *
   * @return string
   *   The generated lock ID.
   */
  protected static function generateLockIdFromFileUri($file_uri) {
    return 'file:ckeditor5:' . Crypt::hashBase64($file_uri);
  }

}
