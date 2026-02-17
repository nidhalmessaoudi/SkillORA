<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Salle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/events')]
#[IsGranted('ROLE_ADMIN')]
class EventController extends AbstractController
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    #[Route('/', name: 'admin_events_index')]
    public function index(): Response
    {
        $events = $this->entityManager->getRepository(Event::class)->findBy([], ['startDate' => 'DESC']);
        $salles = $this->entityManager->getRepository(Salle::class)->findAll();
        $salleMap = [];
        foreach ($salles as $salle) {
            $salleMap[$salle->getId()] = $salle;
        }

        return $this->render('pages/admin/events/index.html.twig', [
            'events' => $events,
            'salle_map' => $salleMap,
        ]);
    }

    #[Route('/export', name: 'admin_events_export')]
    public function export(): Response
    {
        $events = $this->entityManager->getRepository(Event::class)->findBy([], ['startDate' => 'DESC']);
        $salles = $this->entityManager->getRepository(Salle::class)->findAll();

        $salleMap = [];
        foreach ($salles as $salle) {
            $salleMap[$salle->getId()] = $salle->getName() ?? 'Unassigned';
        }

        $rows = [];
        $rows[] = ['ID', 'Title', 'Start Date', 'End Date', 'Type', 'Price', 'Salle'];
        foreach ($events as $event) {
            $rows[] = [
                (string) $event->getId(),
                $event->getTitle() ?? '',
                $event->getStartDate()?->format('Y-m-d H:i') ?? '',
                $event->getEndDate()?->format('Y-m-d H:i') ?? '',
                $event->getEventType() ?? '',
                $event->getPriceType() ?? '',
                $event->getSalleId() ? ($salleMap[$event->getSalleId()] ?? 'Unassigned') : 'Unassigned',
            ];
        }

        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ';');
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        $response = new Response($content);
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'events_export.xls'
        );
        $response->headers->set('Content-Type', 'application/vnd.ms-excel; charset=UTF-8');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    #[Route('/new', name: 'admin_events_new')]
    public function new(Request $request): Response
    {
        $formData = $this->buildEventFormData($request, null, null);
        $errors = [];

        if ($request->isMethod('POST')) {
            $missing = $this->validateEventForm($formData);
            $startDate = $this->parseDate($formData['start_date'] ?? null);
            $endDate = $this->parseDate($formData['end_date'] ?? null);
            $mediaPath = $this->handleMediaUpload($request);
            $salleModelPath = $this->handleSalleModelUpload($request);

            if (!$startDate || !$endDate) {
                $missing[] = 'dates';
            }

            if (!empty($missing)) {
                $messages = [
                    'title' => 'Champ obligatoire.',
                    'start_date' => 'Champ obligatoire.',
                    'end_date' => 'Champ obligatoire.',
                    'event_type' => 'Champ obligatoire.',
                    'price_type' => 'Champ obligatoire.',
                    'price' => 'Champ obligatoire.',
                    'salle_name' => 'Champ obligatoire.',
                    'salle_location' => 'Champ obligatoire.',
                    'salle_max_participants' => 'Champ obligatoire.',
                    'salle_duration' => 'Champ obligatoire.',
                ];
                foreach ($missing as $field) {
                    if ($field === 'dates') {
                        $errors['start_date'] = $messages['start_date'];
                        $errors['end_date'] = $messages['end_date'];
                        continue;
                    }
                    if (isset($messages[$field])) {
                        $errors[$field] = $messages[$field];
                    }
                }
            }

            if (empty($missing)) {
                $salle = new Salle();
                $salle->setName($formData['salle_name']);
                $salle->setImage3d($salleModelPath);
                $salle->setMaxParticipants((int) $formData['salle_max_participants']);
                $salle->setDuration((int) $formData['salle_duration']);
                $salle->setEquipment($formData['salle_equipment'] ?: null);
                $salle->setLocation($this->buildLocationWithCoords(
                    $formData['salle_location'],
                    $formData['salle_lat'],
                    $formData['salle_lng']
                ));
                $this->entityManager->persist($salle);
                $this->entityManager->flush();

                $event = new Event();
                $event->setTitle($formData['title']);
                $event->setDescription($formData['description'] ?: null);
                $event->setStartDate($startDate);
                $event->setEndDate($endDate);
                $event->setEventType($formData['event_type']);
                $event->setPriceType($this->buildPriceTypeValue(
                    $formData['price_type'],
                    $formData['price'] ?? null
                ));
                $event->setImage($mediaPath);
                $event->setSalleId($salle->getId());

                $this->entityManager->persist($event);
                $this->entityManager->flush();

                $this->addFlash('success', 'Event created successfully.');
                return $this->redirectToRoute('admin_events_show', ['id' => $event->getId()]);
            }

            $this->addFlash('error', 'Please fill in all required fields.');
        }

        return $this->render('pages/admin/events/new.html.twig', [
            'form_data' => $formData,
            'errors' => $errors,
        ]);
    }

    #[Route('/{id}', name: 'admin_events_show')]
    public function show(Event $event): Response
    {
        $salle = null;
        if ($event->getSalleId()) {
            $salle = $this->entityManager->getRepository(Salle::class)->find($event->getSalleId());
        }

        return $this->render('pages/admin/events/show.html.twig', [
            'event' => $event,
            'salle' => $salle,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_events_edit')]
    public function edit(Request $request, Event $event): Response
    {
        $salle = null;
        if ($event->getSalleId()) {
            $salle = $this->entityManager->getRepository(Salle::class)->find($event->getSalleId());
        }

        $formData = $this->buildEventFormData($request, $event, $salle);
        $errors = [];

        if ($request->isMethod('POST')) {
            $missing = $this->validateEventForm($formData);
            $startDate = $this->parseDate($formData['start_date'] ?? null);
            $endDate = $this->parseDate($formData['end_date'] ?? null);
            $mediaPath = $this->handleMediaUpload($request);
            $salleModelPath = $this->handleSalleModelUpload($request);

            if (!$startDate || !$endDate) {
                $missing[] = 'dates';
            }

            if (!empty($missing)) {
                $messages = [
                    'title' => 'Champ obligatoire.',
                    'start_date' => 'Champ obligatoire.',
                    'end_date' => 'Champ obligatoire.',
                    'event_type' => 'Champ obligatoire.',
                    'price_type' => 'Champ obligatoire.',
                    'price' => 'Champ obligatoire.',
                    'salle_name' => 'Champ obligatoire.',
                    'salle_location' => 'Champ obligatoire.',
                    'salle_max_participants' => 'Champ obligatoire.',
                    'salle_duration' => 'Champ obligatoire.',
                ];
                foreach ($missing as $field) {
                    if ($field === 'dates') {
                        $errors['start_date'] = $messages['start_date'];
                        $errors['end_date'] = $messages['end_date'];
                        continue;
                    }
                    if (isset($messages[$field])) {
                        $errors[$field] = $messages[$field];
                    }
                }
            }

            if (empty($missing)) {
                if (!$salle) {
                    $salle = new Salle();
                }

                $salle->setName($formData['salle_name']);
                if ($salleModelPath) {
                    $salle->setImage3d($salleModelPath);
                }
                $salle->setMaxParticipants((int) $formData['salle_max_participants']);
                $salle->setDuration((int) $formData['salle_duration']);
                $salle->setEquipment($formData['salle_equipment'] ?: null);
                $salle->setLocation($this->buildLocationWithCoords(
                    $formData['salle_location'],
                    $formData['salle_lat'],
                    $formData['salle_lng']
                ));
                $this->entityManager->persist($salle);

                if (!$salle->getId()) {
                    $this->entityManager->flush();
                }

                $event->setTitle($formData['title']);
                $event->setDescription($formData['description'] ?: null);
                $event->setStartDate($startDate);
                $event->setEndDate($endDate);
                $event->setEventType($formData['event_type']);
                $event->setPriceType($this->buildPriceTypeValue(
                    $formData['price_type'],
                    $formData['price'] ?? null
                ));
                $event->setImage($mediaPath ?? $event->getImage());
                $event->setSalleId($salle->getId());

                $this->entityManager->flush();

                $this->addFlash('success', 'Event updated successfully.');
                return $this->redirectToRoute('admin_events_show', ['id' => $event->getId()]);
            }

            $this->addFlash('error', 'Please fill in all required fields.');
        }

        return $this->render('pages/admin/events/edit.html.twig', [
            'event' => $event,
            'form_data' => $formData,
            'errors' => $errors,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_events_delete', methods: ['POST'])]
    public function delete(Event $event): Response
    {
        $salle = null;
        if ($event->getSalleId()) {
            $salle = $this->entityManager->getRepository(Salle::class)->find($event->getSalleId());
        }

        if (!$salle) {
            $salle = $this->entityManager->getRepository(Salle::class)->findOneBy([
                'eventId' => $event->getId(),
            ]);
        }

        $this->entityManager->remove($event);
        if ($salle) {
            $this->entityManager->remove($salle);
        }
        $this->entityManager->flush();

        $this->addFlash('success', 'Event deleted successfully.');
        return $this->redirectToRoute('admin_events_index');
    }

    private function buildEventFormData(Request $request, ?Event $event, ?Salle $salle): array
    {
        if ($request->isMethod('POST')) {
            $now = new \DateTime();
            $startDate = (string) $request->request->get('start_date', '');
            if ($startDate === '') {
                $startDate = $now->format('Y-m-d\TH:i');
            }

            return [
                'title' => (string) $request->request->get('title', ''),
                'description' => (string) $request->request->get('description', ''),
                'start_date' => $startDate,
                'end_date' => (string) $request->request->get('end_date', ''),
                'event_type' => (string) $request->request->get('event_type', ''),
                'price_type' => (string) $request->request->get('price_type', ''),
                'price' => (string) $request->request->get('price', ''),
                'image' => (string) $request->request->get('image', ''),
                'salle_name' => (string) $request->request->get('salle_name', ''),
                'salle_image_3d' => '',
                'salle_max_participants' => (string) $request->request->get('salle_max_participants', ''),
                'salle_duration' => (string) $request->request->get('salle_duration', ''),
                'salle_equipment' => (string) $request->request->get('salle_equipment', ''),
                'salle_location' => (string) $request->request->get('salle_location', ''),
                'salle_lat' => (string) $request->request->get('salle_lat', ''),
                'salle_lng' => (string) $request->request->get('salle_lng', ''),
            ];
        }

        $locationData = $this->parseLocationWithCoords($salle?->getLocation() ?? '');

        $defaultStartDate = '';
        if (!$event) {
            $defaultStartDate = (new \DateTime())->format('Y-m-d\TH:i');
        }

        $priceTypeValue = (string) ($event?->getPriceType() ?? '');
        $priceType = $priceTypeValue;
        $price = '';
        if (strtolower($priceTypeValue) === 'gratuit') {
            $priceType = 'gratuit';
        } elseif (preg_match('/^\s*([0-9]+(?:[\.,][0-9]+)?)\s*DT\s*$/i', $priceTypeValue, $matches)) {
            $priceType = 'payant';
            $price = str_replace(',', '.', $matches[1]);
        }

        return [
            'title' => $event?->getTitle() ?? '',
            'description' => $event?->getDescription() ?? '',
            'start_date' => $event?->getStartDate()?->format('Y-m-d\TH:i') ?? $defaultStartDate,
            'end_date' => $event?->getEndDate()?->format('Y-m-d\TH:i') ?? '',
            'event_type' => $event?->getEventType() ?? '',
            'price_type' => $priceType,
            'price' => $price,
            'image' => $event?->getImage() ?? '',
            'salle_name' => $salle?->getName() ?? '',
            'salle_image_3d' => $salle?->getImage3d() ?? '',
            'salle_max_participants' => $salle?->getMaxParticipants() ?? '',
            'salle_duration' => $salle?->getDuration() ?? '',
            'salle_equipment' => $salle?->getEquipment() ?? '',
            'salle_location' => $locationData['address'],
            'salle_lat' => $locationData['lat'],
            'salle_lng' => $locationData['lng'],
        ];
    }

    private function validateEventForm(array $data): array
    {
        $required = [
            'title',
            'start_date',
            'end_date',
            'event_type',
            'price_type',
            'salle_name',
            'salle_max_participants',
            'salle_duration',
            'salle_location',
        ];

        $missing = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                $missing[] = $field;
            }
        }

        if (($data['price_type'] ?? '') === 'payant') {
            $price = trim((string) ($data['price'] ?? ''));
            if ($price === '') {
                $missing[] = 'price';
            }
        }

        return $missing;
    }

    private function buildPriceTypeValue(string $priceType, ?string $price): string
    {
        if ($priceType === 'gratuit') {
            return 'gratuit';
        }

        if ($priceType !== 'payant') {
            return $priceType;
        }

        $priceValue = trim((string) $price);
        $normalized = str_replace(',', '.', $priceValue);
        $number = is_numeric($normalized) ? (float) $normalized : null;
        if ($number === null) {
            return 'payant';
        }

        return number_format($number, 0, '.', '') . ' DT';
    }

    private function buildLocationWithCoords(string $address, ?string $lat, ?string $lng): string
    {
        $address = trim($address);
        $lat = trim((string) $lat);
        $lng = trim((string) $lng);

        if ($address === '') {
            return '';
        }

        if ($lat === '' || $lng === '') {
            return $address;
        }

        return sprintf('%s | %s,%s', $address, $lat, $lng);
    }

    private function parseLocationWithCoords(string $location): array
    {
        $location = trim($location);
        $result = [
            'address' => $location,
            'lat' => '',
            'lng' => '',
        ];

        if ($location === '' || !str_contains($location, ' | ')) {
            return $result;
        }

        [$address, $coords] = explode(' | ', $location, 2);
        $coords = trim($coords);
        if (str_contains($coords, ',')) {
            [$lat, $lng] = array_map('trim', explode(',', $coords, 2));
            $result['address'] = $address;
            $result['lat'] = $lat;
            $result['lng'] = $lng;
        }

        return $result;
    }

    private function parseDate(?string $value): ?\DateTimeInterface
    {
        if (!$value) {
            return null;
        }

        try {
            return new \DateTime($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function handleMediaUpload(Request $request): ?string
    {
        $file = $request->files->get('media');
        
        // If no file uploaded, return null (optional field)
        if (!$file instanceof UploadedFile) {
            return null;
        }
        
        // Check if file is valid
        if (!$file->isValid()) {
            $this->addFlash('error', 'File upload error: ' . $file->getErrorMessage());
            return null;
        }

        $mimeType = (string) $file->getClientMimeType();
        if (!str_starts_with($mimeType, 'image/') && !str_starts_with($mimeType, 'video/')) {
            $this->addFlash('error', 'Only image or video files are allowed. Uploaded: ' . $mimeType);
            return null;
        }

        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $targetDir = $this->getParameter('kernel.project_dir') . '/public/uploads/events';
        
        // Ensure directory exists
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        try {
            $file->move($targetDir, $filename);
            $this->addFlash('success', 'Image uploaded successfully!');
        } catch (FileException $e) {
            $this->addFlash('error', 'Upload failed: ' . $e->getMessage());
            return null;
        }

        return '/uploads/events/' . $filename;
    }

    private function handleSalleModelUpload(Request $request): ?string
    {
        $file = $request->files->get('salle_image_3d');
        
        // If no file uploaded, return null (optional field)
        if (!$file instanceof UploadedFile) {
            return null;
        }
        
        // Check if file is valid
        if (!$file->isValid()) {
            $this->addFlash('error', '3D model upload error: ' . $file->getErrorMessage());
            return null;
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($extension, ['glb', 'gltf'], true)) {
            $this->addFlash('error', 'Only .glb or .gltf files are allowed for 3D models. Uploaded: .' . $extension);
            return null;
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $targetDir = $this->getParameter('kernel.project_dir') . '/public/uploads/salles';
        
        // Ensure directory exists
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        try {
            $file->move($targetDir, $filename);
            $this->addFlash('success', '3D model uploaded successfully!');
        } catch (FileException $e) {
            $this->addFlash('error', '3D upload failed: ' . $e->getMessage());
            return null;
        }

        return '/uploads/salles/' . $filename;
    }
}
