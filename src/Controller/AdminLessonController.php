<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\CourseSection;
use App\Entity\Lesson;
use App\Form\LessonType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route("/admin/courses/{courseId}/sections/{sectionId}/lessons")]
class AdminLessonController extends AbstractController
{
    private function getCourseSectionOr404(
        int $courseId,
        int $sectionId,
        EntityManagerInterface $em,
    ): array {
        // Allow both ADMIN and PROFESSOR
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_PROFESSOR')) {
            throw new AccessDeniedException('Access denied. Admin or Professor access required.');
        }

        $course = $em->getRepository(Course::class)->find($courseId);
        if (!$course) {
            throw $this->createNotFoundException("Course not found");
        }

        $section = $em->getRepository(CourseSection::class)->find($sectionId);
        if (!$section || $section->getCourse()->getId() !== $courseId) {
            throw $this->createNotFoundException("Section not found for this course");
        }

        return [$course, $section];
    }

    private function getLessonOr404(
        int $sectionId,
        int $lessonId,
        EntityManagerInterface $em,
    ): Lesson {
        $lesson = $em->getRepository(Lesson::class)->find($lessonId);
        if (!$lesson || $lesson->getSection()->getId() !== $sectionId) {
            throw $this->createNotFoundException("Lesson not found for this section");
        }

        return $lesson;
    }

    private function deleteLocalLessonFile(?string $publicPath): void
    {
        if (!$publicPath) {
            return;
        }

        if (!str_starts_with($publicPath, "/uploads/lessons/")) {
            return;
        }

        $fullPath = $this->getParameter("kernel.project_dir") . "/public" . $publicPath;

        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    // (Optional helper) if you ever want to delete lesson editor images later.
    private function deleteLocalLessonImageFile(?string $publicPath): void
    {
        if (!$publicPath) {
            return;
        }
        if (!str_starts_with($publicPath, "/uploads/lesson-images/")) {
            return;
        }

        $fullPath = $this->getParameter("kernel.project_dir") . "/public" . $publicPath;
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    private function guessUploadedMimeSafely(UploadedFile $file, string $type): string
    {
        $mime = (string) ($file->getClientMimeType() ?? "");

        if ($mime === "") {
            $realPath = $file->getPathname();
            if (is_string($realPath) && $realPath !== "" && is_file($realPath) && is_readable($realPath)) {
                $guessed = $file->getMimeType();
                $mime = is_string($guessed) ? $guessed : "";
            }
        }

        if ($mime === "") {
            $ext = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension() ?: ""));
            if ($type === "pdf" && $ext === "pdf") {
                return "application/pdf";
            }
            if ($type === "video" && $ext !== "") {
                return "video/" . $ext;
            }
        }

        return $mime;
    }

    private function handleLessonUpload(
        Lesson $lesson,
        ?UploadedFile $uploadedFile,
        SluggerInterface $slugger,
        bool $isNew,
        ?string $previousFilePath = null,
    ): void {
        $type = $lesson->getType();
        if (!in_array($type, ["text", "pdf", "video"], true)) {
            return;
        }

        if ($type === "text") {
            if ($previousFilePath) {
                $this->deleteLocalLessonFile($previousFilePath);
            }
            $lesson->setFilePath(null);
            return;
        }

        if (!$uploadedFile) {
            if ($isNew && !$lesson->getFilePath()) {
                throw new \RuntimeException("FILE_REQUIRED");
            }
            return;
        }

        $mime = $this->guessUploadedMimeSafely($uploadedFile, $type);
        $clientExt = strtolower((string) $uploadedFile->getClientOriginalExtension());

        if ($type === "pdf") {
            if ($mime !== "application/pdf" && $clientExt !== "pdf") {
                throw new \RuntimeException("INVALID_PDF");
            }
        }

        if ($type === "video") {
            if (!str_starts_with($mime, "video/") && !in_array($clientExt, ["mp4", "webm", "mov", "m4v"], true)) {
                throw new \RuntimeException("INVALID_VIDEO");
            }
        }

        $subDir = $type === "pdf" ? "pdf" : "video";

        $original = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = $slugger->slug($original)->lower();

        $ext =
            $uploadedFile->guessExtension()
            ?: $uploadedFile->getClientOriginalExtension()
            ?: ($type === "pdf" ? "pdf" : "mp4");

        $newFilename = $safeName . "-" . uniqid("", true) . "." . $ext;

        $targetDir =
            rtrim((string) $this->getParameter("lesson_upload_base"), "/")
            . "/"
            . $subDir;

        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }

        $uploadedFile->move($targetDir, $newFilename);

        if ($previousFilePath) {
            $this->deleteLocalLessonFile($previousFilePath);
        }

        $publicBase = rtrim((string) $this->getParameter("lesson_upload_public_base"), "/");

        $lesson->setFilePath($publicBase . "/" . $subDir . "/" . $newFilename);
        $lesson->setContent(null);
    }

    /**
     * ✅ TinyMCE image upload endpoint (no DB changes)
     * TinyMCE sends multipart/form-data with "file"
     * We respond with: { "location": "/uploads/lesson-images/xxx.png" }
     */
    #[Route("/images/upload", name: "admin_lessons_images_upload", methods: ["POST"])]
    public function uploadEditorImage(
        int $courseId,
        int $sectionId,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
    ): JsonResponse {
        // Reuse your existing access control + course/section validation
        $this->getCourseSectionOr404($courseId, $sectionId, $em);

        /** @var UploadedFile|null $file */
        $file = $request->files->get("file");
        if (!$file instanceof UploadedFile) {
            return new JsonResponse(["error" => "No file uploaded."], 400);
        }

        // Basic validation (images only)
        $mime = (string) ($file->getClientMimeType() ?? "");
        $ext = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension() ?: ""));

        $allowedExt = ["png", "jpg", "jpeg", "gif", "webp"];
        if (!str_starts_with($mime, "image/") && !in_array($ext, $allowedExt, true)) {
            return new JsonResponse(["error" => "Only image files are allowed."], 400);
        }

        $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = $slugger->slug($original)->lower();

        $finalExt = $ext !== "" ? $ext : "png";
        $newFilename = $safeName . "-" . uniqid("", true) . "." . $finalExt;

        $projectDir = (string) $this->getParameter("kernel.project_dir");
        $targetDir = $projectDir . "/public/uploads/lesson-images";

        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }

        try {
            $file->move($targetDir, $newFilename);
        } catch (FileException $e) {
            return new JsonResponse(["error" => "Upload failed."], 500);
        }

        // TinyMCE expects "location"
        $publicPath = "/uploads/lesson-images/" . $newFilename;

        return new JsonResponse(["location" => $publicPath]);
    }

    #[Route("/files/upload", name: "admin_lessons_files_upload", methods: ["POST"])]
    public function uploadEditorFile(
        int $courseId,
        int $sectionId,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
    ): JsonResponse {
        $this->getCourseSectionOr404($courseId, $sectionId, $em);

        /** @var UploadedFile|null $file */
        $file = $request->files->get("file");
        if (!$file instanceof UploadedFile) {
            return new JsonResponse(["error" => "No file uploaded."], 400);
        }

        // ✅ Allow PDFs only
        $mime = (string) ($file->getClientMimeType() ?? "");
        $ext = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension() ?: ""));

        if ($mime !== "application/pdf" && $ext !== "pdf") {
            return new JsonResponse(["error" => "Only PDF files are allowed."], 400);
        }

        $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = $slugger->slug($original)->lower();
        $newFilename = $safeName . "-" . uniqid("", true) . ".pdf";

        $projectDir = (string) $this->getParameter("kernel.project_dir");
        $targetDir = $projectDir . "/public/uploads/lesson-files";

        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }

        try {
            $file->move($targetDir, $newFilename);
        } catch (FileException $e) {
            return new JsonResponse(["error" => "Upload failed."], 500);
        }

        return new JsonResponse([
            "location" => "/uploads/lesson-files/" . $newFilename,
            "filename" => $file->getClientOriginalName(),
        ]);
    }

    #[Route("/new", name: "admin_lessons_new", methods: ["GET", "POST"])]
    public function new(
        int $courseId,
        int $sectionId,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
    ): Response {
        [$course, $section] = $this->getCourseSectionOr404($courseId, $sectionId, $em);

        $lesson = new Lesson();
        $lesson->setSection($section);

        $form = $this->createForm(LessonType::class, $lesson);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            /** @var UploadedFile|null $uploadedFile */
            $uploadedFile = $form->get("upload")->getData();

            try {
                $this->handleLessonUpload($lesson, $uploadedFile, $slugger, true);
            } catch (\RuntimeException $e) {
                if ($e->getMessage() === "FILE_REQUIRED") {
                    $form->get("upload")->addError(new FormError("File is required for PDF/Video lessons."));
                } elseif ($e->getMessage() === "INVALID_PDF") {
                    $form->get("upload")->addError(new FormError("Please upload a valid PDF file."));
                } elseif ($e->getMessage() === "INVALID_VIDEO") {
                    $form->get("upload")->addError(new FormError("Please upload a valid video file."));
                } else {
                    $form->addError(new FormError("Upload failed."));
                }
            }

            if ($form->isValid()) {
                $em->persist($lesson);
                $em->flush();

                return $this->redirectToRoute("admin_sections_show", [
                    "courseId" => $courseId,
                    "sectionId" => $sectionId,
                ]);
            }
        }

        return $this->render("pages/admin/lessons/new.html.twig", [
            "course" => $course,
            "section" => $section,
            "form" => $form->createView(),
        ]);
    }

    #[Route("/{lessonId}", name: "admin_lessons_show", methods: ["GET"])]
    public function show(
        int $courseId,
        int $sectionId,
        int $lessonId,
        EntityManagerInterface $em,
    ): Response {
        [$course, $section] = $this->getCourseSectionOr404($courseId, $sectionId, $em);

        $lesson = $this->getLessonOr404($section->getId(), $lessonId, $em);

        return $this->render("pages/admin/lessons/show.html.twig", [
            "course" => $course,
            "section" => $section,
            "lesson" => $lesson,
        ]);
    }

    #[Route("/{lessonId}/edit", name: "admin_lessons_edit", methods: ["GET", "POST"])]
    public function edit(
        int $courseId,
        int $sectionId,
        int $lessonId,
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
    ): Response {
        [$course, $section] = $this->getCourseSectionOr404($courseId, $sectionId, $em);

        $lesson = $this->getLessonOr404($section->getId(), $lessonId, $em);
        $previousFilePath = $lesson->getFilePath();

        $form = $this->createForm(LessonType::class, $lesson);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            /** @var UploadedFile|null $uploadedFile */
            $uploadedFile = $form->get("upload")->getData();

            try {
                $this->handleLessonUpload($lesson, $uploadedFile, $slugger, false, $previousFilePath);
            } catch (\RuntimeException $e) {
                if ($e->getMessage() === "FILE_REQUIRED") {
                    $form->get("upload")->addError(new FormError("File is required for PDF/Video lessons."));
                } elseif ($e->getMessage() === "INVALID_PDF") {
                    $form->get("upload")->addError(new FormError("Please upload a valid PDF file."));
                } elseif ($e->getMessage() === "INVALID_VIDEO") {
                    $form->get("upload")->addError(new FormError("Please upload a valid video file."));
                } else {
                    $form->addError(new FormError("Upload failed."));
                }
            }

            if ($form->isValid()) {
                $em->flush();

                return $this->redirectToRoute("admin_lessons_show", [
                    "courseId" => $courseId,
                    "sectionId" => $sectionId,
                    "lessonId" => $lessonId,
                ]);
            }
        }

        return $this->render("pages/admin/lessons/edit.html.twig", [
            "course" => $course,
            "section" => $section,
            "lesson" => $lesson,
            "form" => $form->createView(),
        ]);
    }

    #[Route("/{lessonId}/delete", name: "admin_lessons_delete", methods: ["POST"])]
    public function delete(
        int $courseId,
        int $sectionId,
        int $lessonId,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        [$course, $section] = $this->getCourseSectionOr404($courseId, $sectionId, $em);

        $lesson = $this->getLessonOr404($section->getId(), $lessonId, $em);

        if ($this->isCsrfTokenValid("delete_lesson_" . $lesson->getId(), (string) $request->request->get("_token"))) {
            $this->deleteLocalLessonFile($lesson->getFilePath());
            $em->remove($lesson);
            $em->flush();
        }

        return $this->redirectToRoute("admin_sections_show", [
            "courseId" => $courseId,
            "sectionId" => $sectionId,
        ]);
    }
}
