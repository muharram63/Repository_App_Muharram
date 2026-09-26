<?php

namespace App\Services\Ai;

use App\Models\AiCandidateDocument;
use App\Models\AiInterviewCriterion;
use App\Models\Resume;

/**
 * Разбор документов кандидата.
 *
 * Три разных работы, поэтому три метода и три обращения к модели, а не одно
 * большое. Смешав их, мы получили бы ответ, в котором нельзя понять, что именно
 * привело к какому выводу, — а объяснять решение придётся.
 *
 * Про подлинность здесь же, чтобы не забылось: система не умеет отличать
 * настоящий диплом от поддельного и не делает вид, что умеет. Она сверяет
 * данные документа с резюме и отмечает расхождения. Обвинять кандидата в
 * подделке она не должна ни при каких обстоятельствах.
 */
interface DocumentAuditor
{
    public function available(): bool;

    /**
     * Резюме в структурированный вид.
     *
     * @return array{
     *     profession:string, experience_years:int, skills:array<int,string>,
     *     languages:array<int,string>, positions:array<int,array{company:string,role:string,period:string,summary:string}>,
     *     education:array<int,array{place:string,program:string,year:string}>,
     *     achievements:array<int,string>, enough_data:bool
     * }
     *
     * @throws AiUnavailableException
     * @throws AiInvalidAnswerException
     */
    public function parseResume(Resume $resume): array;

    /**
     * Проверка одного документа: сверка с резюме и отношение к вакансии.
     *
     * @param  array  $parsedResume  результат parseResume
     * @return array{
     *     readable:bool, kind:string, person:?string, institution:?string,
     *     program:?string, year:?string, matches_resume:string,
     *     mismatches:array<int,string>, relevance:string, note:string
     * }
     *
     * @throws AiUnavailableException
     * @throws AiInvalidAnswerException
     */
    public function auditDocument(AiCandidateDocument $document, array $parsedResume): array;

    /**
     * Сверка требований вакансии с резюме и документами.
     *
     * @param  \Illuminate\Support\Collection<int,AiInterviewCriterion>  $criteria
     * @param  array  $parsedResume  результат parseResume
     * @param  array<int,array>  $documentFindings  вердикты по документам
     * @return array{
     *     checks:array<int,array{key:string,status:string,evidence:string,confidence:int}>,
     *     inconsistencies:array<int,string>,
     *     questions:array<int,string>,
     *     enough_data:bool
     * }
     *
     * @throws AiUnavailableException
     * @throws AiInvalidAnswerException
     */
    public function buildMatrix($criteria, array $parsedResume, array $documentFindings): array;
}
