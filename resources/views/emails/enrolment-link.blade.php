<x-mail::message>
# Complete your registration

Dear {{ $enrolment->student?->first_name ?? 'Student' }},

You have been registered in the following course:

**{{ $enrolment->course?->title ?? 'Your selected course' }}**

<x-mail::panel>
**PLEASE SCROLL DOWN AND READ ALL INFORMATION BELOW**
</x-mail::panel>

To complete your registration, click the button below:

<x-mail::button :url="$enrolment->enrolment_link">
Complete your registration
</x-mail::button>

If the button does not work, copy and paste this link into your browser:

{{ $enrolment->enrolment_link }}

Your registration information is as follows:

**Student Name:** {{ $enrolment->student?->full_name }}  
**Student Email:** {{ $enrolment->student?->email }}

If an error has been made during the registration, either with your name or email address, please send us an email after completing this enrolment form and we will update your details.

For full course details and entry requirements, please visit [amstraining.com.au](https://amstraining.com.au).

Yours faithfully,  
Ian Cole  
AMS Training
</x-mail::message>