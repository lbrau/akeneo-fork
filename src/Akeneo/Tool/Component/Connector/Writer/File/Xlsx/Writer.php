<?php

namespace Akeneo\Tool\Component\Connector\Writer\File\Xlsx;

use Akeneo\Tool\Component\Batch\Item\FlushableInterface;
use Akeneo\Tool\Component\Batch\Item\InitializableInterface;
use Akeneo\Tool\Component\Batch\Item\ItemWriterInterface;
use Akeneo\Tool\Component\Buffer\BufferFactory;
use Akeneo\Tool\Component\Connector\ArrayConverter\ArrayConverterInterface;
use Akeneo\Tool\Component\Connector\Job\JobFileBackuper;
use Akeneo\Tool\Component\Connector\Writer\File\AbstractFileWriter;
use Akeneo\Tool\Component\Connector\Writer\File\FlatItemBuffer;
use Akeneo\Tool\Component\Connector\Writer\File\FlatItemBufferFlusher;
use Akeneo\Tool\Component\Connector\Writer\File\WrittenFileInfo;

/**
 * Write simple data into a XLSX file on the local filesystem
 *
 * @author    Willy Mesnage <willy.mesnage@akeneo.com>
 * @copyright 2016 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Writer extends AbstractFileWriter implements ItemWriterInterface, InitializableInterface, FlushableInterface
{
    /** @var ArrayConverterInterface */
    protected $arrayConverter;

    /** @var FlatItemBuffer */
    protected $flatRowBuffer = null;

    /** @var FlatItemBufferFlusher */
    protected $flusher;

    /** @var BufferFactory */
    protected $bufferFactory;

    protected array $state = [];

    public function __construct(
        ArrayConverterInterface $arrayConverter,
        BufferFactory $bufferFactory,
        FlatItemBufferFlusher $flusher,
        private readonly JobFileBackuper $jobFileBackuper,
    ) {
        parent::__construct();

        $this->arrayConverter = $arrayConverter;
        $this->bufferFactory = $bufferFactory;
        $this->flusher = $flusher;
    }

    /**
     * {@inheritdoc}
     */
    public function initialize(): void
    {
        $bufferFilePath = $this->state['buffer_file_path'] ?? null;

        if ($bufferFilePath) {
            $this->jobFileBackuper->recover($this->stepExecution->getJobExecution(), $bufferFilePath);
        }

        $this->flatRowBuffer = $this->bufferFactory->create($bufferFilePath);

        if (array_key_exists('headers', $this->state)) {
            $this->flatRowBuffer->addToHeaders($this->state['headers']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function write(array $items): void
    {
        $exportFolder = dirname($this->getPath());
        if (!is_dir($exportFolder)) {
            $this->localFs->mkdir($exportFolder);
        }

        $flatItems = [];
        foreach ($items as $item) {
            $flatItems[] = $this->arrayConverter->convert($item);
        }

        $parameters = $this->stepExecution->getJobParameters();
        $options = [];
        $options['withHeader'] = $parameters->get('withHeader');
        $this->flatRowBuffer->write($flatItems, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): void
    {
        $this->flusher->setStepExecution($this->stepExecution);

        $writerOptions = ['type' => 'xlsx'];

        $writtenFiles = $this->flusher->flush(
            $this->flatRowBuffer,
            $writerOptions,
            $this->getPath(),
            $this->stepExecution->getJobParameters()->get('linesPerFile')
        );

        foreach ($writtenFiles as $writtenFile) {
            $this->writtenFiles[] = WrittenFileInfo::fromLocalFile($writtenFile, \basename($writtenFile));
        }
    }

    public function getState(): array
    {
        if (null === $this->flatRowBuffer) {
            return [];
        }

        $filePath = $this->flatRowBuffer->getFilePath();
        $this->jobFileBackuper->backup($this->stepExecution->getJobExecution(), $filePath);

        return [
            'buffer_file_path' => $filePath,
            'headers' => $this->flatRowBuffer->getHeaders(),
        ];
    }

    public function setState(array $state): void
    {
        $this->state = $state;
    }
}
