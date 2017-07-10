<?php

namespace Louvre\BookingBundle\Controller;

use Louvre\BookingBundle\Entity\Booking;
use Louvre\BookingBundle\Repository\BookingRepository;
use Louvre\BookingBundle\Entity\Ticket;
use Louvre\BookingBundle\Entity\Billet;
use Louvre\BookingBundle\Form\BookingType;
use Louvre\BookingBundle\Form\BilletType;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

use Symfony\Component\Validator\Constraints\DateTime;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;



class BookingController extends Controller
{
	public function indexAction(Request $request)
	{
		date_default_timezone_set('Europe/Paris');

		$session = $request->getSession();
		
		$booking = new Booking();


		$form = $this->createForm(BookingType::class, $booking);

		if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) 
		{
			$ticketsSold = ticketsSold($booking->getDate());
			var_dump($ticketsSold);
			var_dump($booking->getDate());

			$session->set('booking', $booking);

			return $this->redirectToRoute('louvre_booking_ticket');

		}

		return $this->render('LouvreBookingBundle:Booking:index.html.twig', array(
			'form' => $form->createView(),
			));
	}

	public function ticketAction(Request $request)
	{
		$billet = new Billet();
		$booking = $request->getSession()->get('booking');

		

		$nbTickets = $booking->getNbTickets();

		$reference = $booking->getReference();

		

		$tickets = array();
		for ($i = 1; $i <= $nbTickets; $i++)
		{		
			$tickets[$i] = new Ticket();
			$tickets[$i]->setReference($reference);

			$billet->getTickets()->add($tickets[$i]);
		}

		$form = $this->createForm(BilletType::class, $billet);
		

		if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {

			$request->getSession()->set('tickets', $tickets);
			$tickets = $request->getSession()->get('tickets');
			

			$date1 = new \Datetime('now');

			for ($i = 1; $i <= $nbTickets; $i++)
			{
      			$birthdate = $tickets[$i]->getAge();

				$diff = date_diff($birthdate, $date1);
				$age = (int) $diff->format("%a");
				$age /= 365.25;

				
				if ($age < 4)
				{
					$tickets[$i]->setPrice(0);
				}
				elseif ($age >= 4 && $age <= 12 ) 
				{
					$tickets[$i]->setPrice(8);
				}
				elseif ($age >= 60) 
				{
					$tickets[$i]->setPrice(12);
				}
				elseif($tickets[$i]->getDiscount())
				{
					$tickets[$i]->setPrice(10);
				}
				else
				{
					$tickets[$i]->setPrice(16);
				}
				
			}


			
			

			return $this->redirectToRoute('louvre_booking_review');

			$request->getSession()->getFlashBag()->add('success','Votre reservation a bien ete prise en compte.');

		}


		return $this->render('LouvreBookingBundle:Booking:ticket.html.twig', array(
			'form' => $form->createView(),
			'booking' => $booking,
						
			));
	}

	public function recapAction(Request $request)
	{
		$session = $request->getSession();

		$booking = $session->get('booking');
		$tickets = $session->get('tickets');
		
		return $this->render('LouvreBookingBundle:Booking:recap.html.twig', array(
			'booking' => $booking,
			'tickets' => $tickets,
			
		));
	}


	public function confirmAction(Request $request)
	{
		$booking = $request->getSession()->get('booking');
		$tickets = $request->getSession()->get('tickets');
		$amount = 0;
		//Calcul du montant total de la commande
		for($i = 1; $i <= $booking->getNbTickets(); $i++)
		{
			$amount += ($tickets[$i]->getPrice()*100);
		}


		// Set your secret key: remember to change this to your live secret key in production
		// See your keys here: https://dashboard.stripe.com/account/apikeys
		\Stripe\Stripe::setApiKey("sk_test_rv7QCc1V2Pk38fdU0wT2dUrT");

		// Token is created using Stripe.js or Checkout!
		// Get the payment token submitted by the form:
		$token = $_POST['stripeToken'];

		// Charge the user's card:
		$charge = \Stripe\Charge::create(array(
		  "amount" => $amount,
		  "currency" => "eur",
		  "description" => "Example charge",
		  "source" => $token,
		));

		$em = $this->getDoctrine()->getManager();
		$em->persist($booking);
		foreach($tickets as $ticket)
		{
			$em->persist($ticket);
		}
		$em->flush();


		$recipient = $booking->getEmail();
		// Email to be sent once payment process is finished
		$message = (new \Swift_Message('Louvre Confirmation commande'))
			->setFrom('thomas.proust89@gmail.com')
			->setTo($recipient)
			->setBody('test')
				
			;
		$this->get('mailer')->send($message);

		return $this->render('LouvreBookingBundle:Booking:confirm.html.twig');
	}
}