<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Json;
use App\Core\Request;
use App\Core\Response;
use App\Http\Controllers\Controller;

/** Services, skills, timeline, testimonials and the résumé endpoint group. */
final class ContentController extends Controller
{
    public function services(Request $request): Response
    {
        $locale = $this->locale($request);

        return Response::json([
            'data' => $this->content()->services($locale, $request->boolean('featured')),
            'locale' => $locale,
        ])->withCache(300);
    }

    public function service(Request $request): Response
    {
        $locale = $this->locale($request);
        $service = $this->content()->serviceBySlug((string) $request->routeParam('slug'), $locale);

        if ($service === null) {
            $this->notFound('Service');
        }

        $service['features'] = Json::list($service['features_json'] ?? null);
        unset($service['features_json']);

        return $this->respond($service, $locale)->withCache(300);
    }

    public function skills(Request $request): Response
    {
        $locale = $this->locale($request);
        $kind = (string) $request->query('kind', 'skill');
        $skills = $this->content()->skills($locale, in_array($kind, ['skill', 'technology'], true) ? $kind : 'skill');

        return $this->respond([
            'items' => $skills['items'],
            'groups' => $skills['groups'],
            'categories' => $this->content()->skillCategories($locale),
            'technologies' => $this->content()->featuredTechnologies($locale, 40),
        ], $locale)->withCache(300);
    }

    public function timeline(Request $request): Response
    {
        $locale = $this->locale($request);

        return $this->respond([
            'experiences' => $this->content()->experiences($locale),
            'education' => $this->content()->education($locale),
            'certifications' => $this->content()->certifications($locale),
        ], $locale)->withCache(300);
    }

    public function experiences(Request $request): Response
    {
        $locale = $this->locale($request);

        return $this->respond($this->content()->experiences($locale), $locale)->withCache(300);
    }

    public function education(Request $request): Response
    {
        $locale = $this->locale($request);

        return $this->respond($this->content()->education($locale), $locale)->withCache(300);
    }

    public function certifications(Request $request): Response
    {
        $locale = $this->locale($request);

        return $this->respond($this->content()->certifications($locale), $locale)->withCache(300);
    }

    public function testimonials(Request $request): Response
    {
        $locale = $this->locale($request);

        return $this->respond($this->content()->testimonials($locale), $locale)->withCache(300);
    }

    public function resume(Request $request): Response
    {
        $locale = $this->locale($request);
        $resume = $this->content()->resume($locale);

        if ($resume === null) {
            $this->notFound('Résumé');
        }

        return $this->respond($resume + [
            'experiences' => $this->content()->experiences($locale),
            'education' => $this->content()->education($locale),
            'certifications' => $this->content()->certifications($locale),
            'skills' => $this->content()->skills($locale),
            'projects' => $this->content()->projects($locale, ['featured' => true], 6, 1)['items'],
            'technologies' => $this->content()->featuredTechnologies($locale, 30),
        ], $locale);
    }

    /** Streams the uploaded PDF for the requested language and counts the download. */
    public function download(Request $request): Response
    {
        $locale = $this->locale($request);
        $resume = $this->content()->resume($locale);

        if ($resume === null || ($resume['file_url'] ?? null) === null) {
            $this->notFound('Résumé file');
        }

        $media = \App\Core\Database::table('media')->where('id', (int) (
            \App\Core\Database::table('resumes')->where('id', (int) $resume['id'])->value('file_media_id') ?? 0
        ))->first();

        if ($media === null) {
            $this->notFound('Résumé file');
        }

        $path = \App\Core\Config::get('app.public_path') . (string) $media['url'];

        if (!is_file($path)) {
            $this->notFound('Résumé file');
        }

        \App\Core\Database::table('resumes')->where('id', (int) $resume['id'])->increment('download_count');
        \App\Core\AuditLogger::log(null, 'resume.download', 'resumes', (int) $resume['id'], [
            'lang' => $locale,
            'ip' => $request->ip(),
        ]);

        return Response::download($path, sprintf('resume-%s-%s.pdf', $locale, date('Y-m')), 'application/pdf');
    }
}
