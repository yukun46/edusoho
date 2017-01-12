<?php
namespace AppBundle\Controller\Classroom;

use Symfony\Component\HttpFoundation\Request;
use Topxia\WebBundle\Controller\BaseController;

class CourseLessonController extends BaseController
{
    public function previewAction(Request $request, $classroomId, $courseId)
    {
        $lessonId  = $request->query->get('lessonId', 0);
        $course    = $this->getCourseService()->getCourse($courseId);
        $lesson    = $this->getCourseService()->getCourseLesson($courseId, $lessonId);
        $classroom = $this->getClassroomService()->getClassroom($classroomId);
        $user      = $this->getCurrentUser();
        $member    = $user['id'] ? $this->getClassroomService()->getClassroomMember($classroom['id'], $user['id']) : null;

        if (!$user->isLogin()) {
            return $this->forward('AppBundle:CourseLesson:preview', array(
                'courseId' => $courseId,
                'lessonId' => $lessonId
            ));
        }

        if ($lesson['free'] || $course['tryLookable'] || ($member && !$member['locked'])) {
            return $this->forward('AppBundle:CourseLesson:preview', array(
                'courseId' => $courseId,
                'lessonId' => $lessonId
            ));
        }

        return $this->redirect($this->generateUrl('classroom_buy_hint', array('courseId' => $course["id"])));
    }

    public function buyHintAction(Request $request, $courseId)
    {
        $classroom = $this->getClassroomService()->getClassroomByCourseId($courseId);

        return $this->render('classroom/hint-modal.html.twig', array(
            'classroom' => $classroom
        ));
    }

    public function listAction(Request $request, $classroomId, $courseId)
    {
        $user   = $this->getCurrentUser();
        $member = $user['id'] ? $this->getClassroomService()->getClassroomMember($classroomId, $user['id']) : null;
        return $this->render('classroom/course/lessons-list.html.twig', array(
            'classroomId' => $classroomId,
            'courseId'    => $courseId,
            'member'      => $member
        ));
    }

    private function getClassroomService()
    {
        return $this->createService('Classroom:ClassroomService');
    }

    private function getCourseService()
    {
        return $this->createService('Course:CourseService');
    }
}