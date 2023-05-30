<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\GlobalFile;
use Str;
use Validator;
use App\Models\CompanyContact;
use App\Models\Note;
use App\Models\NoteType;
use Carbon\Carbon;
use App\Models\FolderType;
use App\Models\Folder;
use App\Models\FolderFile;
use App\Http\Controllers\Functions;
use App\Models\Protocol;

class GlobalFileController extends Controller
{
    use Functions;

    /* Listing file */
    public function list(Request $request)
    {
        $this->validate($request, [
            'search'          => 'nullable',
            'company_related' => 'nullable|exists:contacts,id',
            'contact_related' => 'nullable|exists:contacts,id',
            'is_message'      => 'required|boolean',
        ]);
        $query = GlobalFile::where('company_id', auth()->user()->company_id)->with('user:id,first_name,last_name')->with('companyContact:id,company_name', 'peopleContact:id,first_name,last_name');

        /* Get records of files particular company contact */
        if ($request->company_related) {
            $query =  $query->where('company_related', $request->company_related);
        }
        /* Get records of files particular people contact */
        if ($request->contact_related) {
            $query =   $query->where('contact_related', $request->contact_related);
        }
        /*Search functionality */
        if ($request->search) {
            $search = $request->search;
            $query = $query->where(function ($query) use ($search, $request) {
                $query->where('file_name', 'LIKE', '%' . $search . '%')
                    ->when($request->is_message, function ($q) use ($search) {
                        $q->orWhere('message', 'LIKE', '%' . $search . '%');
                    })
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('first_name', 'LIKE', '%' . $search . '%')
                            ->orWhere('last_name', 'LIKE', '%' . $search . '%');
                    })
                    ->orWhereHas('companyContact', function ($q) use ($search) {
                        $q->where('company_name', 'LIKE', '%' . $search . '%');
                    })
                    ->orWhereHas('peopleContact', function ($q) use ($search) {
                        $q->where('first_name', 'LIKE', '%' . $search . '%')
                            ->orWhere('last_name', 'LIKE', '%' . $search . '%');
                    });
            });
        }

        /* For Pagination functionality*/
        $count = $query->count();
        if ($request->page && $request->perPage) {
            $page       = $request->page;
            $perPage    = $request->perPage;
            $query      = $query->skip($perPage * ($page - 1))->take($perPage);
        }
        $files = $query->orderBy('created_at', 'DESC')->get();

        return ok(__('File list'), [
            'files' => $files,
            'count' => $count
        ]);
    }

    /* Store multiple file upload */
    public function store(Request $request)
    {

        $this->validate($request, [
            'uploaded_file'   => 'required',
            'uploaded_file.*' =>  'mimes:png,csv,pdf,doc,docx,txt,xls,xlsx,jpg,jpeg|max:10240',
            'message'         => 'nullable|max:1000',
            'contact_related' => 'nullable|exists:contacts,id',
            'company_related' => 'nullable|exists:contacts,id',
        ], [
            'uploaded_file.*.max' => 'The Uploaded file must not be greater than 10 MB. '
        ]);
        /* Check company or contact to same company or another company */
        $query   = CompanyContact::query();
        $company = (clone $query)->where('company_id', auth()->user()->company_id)->where('id', $request->company_related)->where('sub_type', "C")->exists();

        $contact = (clone $query)->where('company_id', auth()->user()->company_id)->where('sub_type', 'P')->where('id', $request->contact_related)->exists();

        if (!$company && $request->company_related) {
            return error(_('Can not add file to another team member company or company contact'), [], 'validation');
        }
        if (!$contact && $request->contact_related) {
            return error(_('Can not add file to another team member company or company contact'), [], 'validation');
        }
        /* Upload files*/
        $files_added = count($request->uploaded_file);

        if ($request->has('uploaded_file')) {
            $upload_files = [];
            foreach ($request->uploaded_file as $key => $value) {

                $file            = $value;
                $directory       = 'global_files';
                $upload_file     = uploadFile($file, $directory);
                $filename        = $file->getClientOriginalName();
                $extension       = $file->getClientOriginalExtension();

                /* Check file extension null or not */
                if (empty($extension)) {
                    return error(__('The uploaded_file must be a file of type: pdf, csv, xls, doc, docx, jpg, jpeg, png, text/plain'), [], 'validation');
                }
                if ($extension == "sql" || $extension == "SQL") {
                    return error(__('The uploaded_file must be a file of type: pdf, csv, xls, doc, docx, jpg, jpeg, png, text/plain'), [], 'validation');
                }
                $upload_files[] = [
                    'id'             => Str::uuid(),
                    'company_id'     => auth()->user()->company_id,
                    'uploaded_file'  => $upload_file,
                    'message'        => $request->message,
                    'file_name'      => $filename,
                    'file_type'      => $extension,
                    'created_by'     => auth()->user()->id,
                    'company_related' => $request->company_related,
                    'contact_related' => $request->contact_related,
                    'created_at'     => Carbon::now(),
                ];
            }
            GlobalFile::insert($upload_files);

            /* Sent notification when upload file */
            if ($request->company_related || $request->contact_related) {
                $compnyName  = (clone $query)->where('id', $request->company_related)->where('sub_type', 'C')->first();
                $contactName = (clone $query)->where('id', $request->contact_related)->where('sub_type', 'P')->first();

                $protocolmessage = auth()->user()->first_name . ' ' . auth()->user()->last_name . ' has added ';
                if ($files_added === 1) {
                    $protocolmessage .= 'a file';
                } else {
                    $protocolmessage .= $files_added . ' files';
                }

                if ($compnyName && $contactName) {
                    $message = auth()->user()->first_name . ' ' . auth()->user()->last_name . '' . ' has added a file to the' . $compnyName->company_name . ',' . $contactName->first_name . ' ' . $contactName->last_name . "'" . 's profile';

                    $this->addProtocol($contactName->id, 'files', $protocolmessage);
                    $this->addProtocol($compnyName->id, 'files', $protocolmessage);
                } else if ($compnyName) {
                    $message = auth()->user()->first_name . ' ' . auth()->user()->last_name . '' . ' has added a file to the ' . $compnyName->company_name . "'" . 's profile';

                    $this->addProtocol($compnyName->id, 'files', $protocolmessage);
                } else {
                    $message = auth()->user()->first_name . ' ' . auth()->user()->last_name . '' . ' has added a file to the ' . $contactName->first_name . ' ' . $contactName->last_name . "'" . 's profile';

                    $this->addProtocol($contactName->id, 'files', $protocolmessage);
                }
                $type  = 'file';
                sentNotification($message, $type);
            }

            return ok(__('File(s) uploaded successfully'), $upload_files);
        }
    }

    /*Delete files */
    public function delete(Request $request)
    {
        $this->validate($request, [
            'file_id'     => 'required|array|exists:global_files,id,deleted_at,NULL',
            'contact_id'  => 'required|exists:contacts,id',
        ]);
        $query = GlobalFile::query();
        /* Check file */
        $count = $query->whereIn('id', $request->file_id)->where('company_id', auth()->user()->company_id)->count();
        $requestCount = count($request->file_id);
        if ($count != $requestCount) {
            return error(__('Can not delete file of another member'), [], 'validation');
        }

        $files_deleted = count($request->file_id);

        $query->whereIn('id', $request->file_id)->where('company_id', auth()->user()->company_id)->delete();

        $message = auth()->user()->first_name . ' ' . auth()->user()->last_name . ' has deleted ';
        if ($files_deleted === 1) {
            $message .= 'a file';
            $print = 'file has';
        } else {
            $message .= $files_deleted . ' files';
            $print = $files_deleted . ' files have';
        }

        $protocol = [];
        $protocol['contact_id'] = $request->contact_id;
        $protocol['category'] =  'files';
        $protocol['message'] = $message;
        $protocol['icon']      = 'pin.svg';
        $protocol = Protocol::create($protocol);

        return ok(__('The selected ' . $print . ' been successfully deleted'));
    }

    /*Delete global files */
    public function deleteglobalfiles(Request $request)
    {
        $this->validate($request, [
            'file_id'     => 'required|array|exists:global_files,id,deleted_at,NULL',
        ]);
        $query = GlobalFile::query();
        /* Check file */
        $count = $query->whereIn('id', $request->file_id)->where('company_id', auth()->user()->company_id)->count();
        $requestCount = count($request->file_id);
        if ($count != $requestCount) {
            return error(__('Can not delete file of another member'), [], 'validation');
        }

        $files_deleted = count($request->file_id);

        if ($files_deleted === 1) {
            $message = 'file has';
        } else {
            $message = $files_deleted . ' files have';
        }

        $query->whereIn('id', $request->file_id)->where('company_id', auth()->user()->company_id)->delete();

        return ok(__('The selected ' . $message . ' been successfully deleted'));
    }

    /*Delete folder files */
    public function deletfolderfiles(Request $request)
    {
        $this->validate($request, [
            'file_id' => 'required|array|exists:global_files,id,deleted_at,NULL',
            'folder_id'   => 'required|exists:folders,id',
        ]);

        $query = FolderFile::query();
        /* Check file */
        $count = $query->whereIn('file_id', $request->file_id)->where('folder_id', $request->folder_id)->count();
        $requestCount = count($request->file_id);
        if ($count != $requestCount) {
            return error(__('The files could not be found in the specified folder.'));
        }

        $query->delete();

        GlobalFile::whereIn('id', $request->file_id)->delete();

        return ok(__('The selected file(s) have been successfully deleted'));
    }

    /* create new folder and add multiple files*/
    public function newfolder(Request $request)
    {

        $this->validate($request, [
            'contact_id'      =>  'required|exists:contacts,id',
            'uploaded_file'   => 'required',
            'uploaded_file.*' => 'mimes:csv,pdf,xls,doc,docx,jpg,jpeg,png,txt|max:10240',
            'name'            => 'nullable|max:1000',
            'description'     => 'nullable|max:1000',
            'folder_type_id'  => 'nullable|exists:folder_types,id',
        ], [
            'uploaded_file.*.max' => 'The Uploaded file must not be greater than 10 MB. '
        ]);

        /* create folder*/
        $folder = [];
        $folder['name'] = $request->name;
        $folder['description'] =  $request->description;
        $folder['contact_id'] = $request->contact_id;
        $folder['folder_type_id'] =  $request->folder_type_id;
        $Newfolder = Folder::create($folder);

        /* Upload files*/
        if ($request->has('uploaded_file')) {
            $upload_files = [];
            foreach ($request->uploaded_file as $key => $value) {

                $file            = $value;
                $directory       = 'global_files';
                $upload_file     = uploadFile($file, $directory);
                $filename        = $file->getClientOriginalName();
                $extension       = $file->getClientOriginalExtension();

                /* Check file extension null or not */
                if (empty($extension)) {
                    return error(__('The uploaded_file must be a file of type: pdf, csv, xls, doc, docx, jpg, jpeg, png, text/plain'), [], 'validation');
                }
                if ($extension == "sql" || $extension == "SQL") {
                    return error(__('The uploaded_file must be a file of type: pdf, csv, xls, doc, docx, jpg, jpeg, png, text/plain'), [], 'validation');
                }

                $upload_files = [];
                $upload_files['id'] = Str::uuid();
                $upload_files['company_id'] = auth()->user()->company_id;
                $upload_files['contact_id'] =  $request->contact_id;
                $upload_files['uploaded_file'] = $upload_file;
                $upload_files['file_name'] = $filename;
                $upload_files['file_type'] = $extension;
                $upload_files['created_by'] = auth()->user()->id;
                $upload_files['created_at'] = Carbon::now();
                $GlobalFile = GlobalFile::create($upload_files);

                $folder_files = [];
                $folder_files['folder_id'] = $Newfolder->id;
                $folder_files['file_id'] = $GlobalFile->id;
                $FolderFile = FolderFile::create($folder_files);
            }

            return ok(__('Folder created successfully'), $Newfolder);
        }
    }

    /* create new folder and add multiple files*/
    public function listfiles(Request $request)
    {

        $this->validate($request, [
            'folder_id'   => 'required|exists:folders,id',
            'page'      => 'required|integer|min:1',
            'perPage'   => 'required|integer|min:1',
            'search'    => 'nullable'
        ]);

        $Folder = Folder::findOrFail($request->folder_id);
        if (!$Folder) {
            return $this->sendResponse(false, 'Unable to find the Folder.');
        }

        $folderFile = FolderFile::where('folder_id', $request->folder_id)->pluck('file_id')->toArray();

        $query = GlobalFile::whereIn('id', $folderFile)->with('user:id,first_name,last_name')->with('contact:id,company_name,first_name,last_name');

        /*Search functionality */
        if ($request->search) {
            $search = $request->search;
            $query = $query->where(function ($query) use ($search) {
                $query->where('file_name', 'LIKE', '%' . $search . '%')
                    ->orWhere('message', 'LIKE', '%' . $search . '%')
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('first_name', 'LIKE', '%' . $search . '%')
                            ->orWhere('last_name', 'LIKE', '%' . $search . '%');
                    })->orWhereHas('contact', function ($q) use ($search) {
                        $q->where('first_name', 'LIKE', '%' . $search . '%')
                            ->orWhere('last_name', 'LIKE', '%' . $search . '%')
                            ->orWhere('company_name', 'LIKE', '%' . $search . '%');
                    });
            });
        }

        /**For pagination */
        $paginateDetails = $this->getPaginationDetails($request->page, $request->perPage);
        $page    = $paginateDetails['page'];
        $perPage = $paginateDetails['perPage'];
        $count = $query->count();
        $query->skip($perPage * ($page - 1))->take($perPage);

        $files = $query->orderBy('created_at', 'DESC')->get();

        return ok(__('File list'), [
            'files' => $files,
            'count' => $count
        ]);
    }

    /* List note type */

    public function listNoteType(Request $request)
    {
        $noteType = NoteType::where('company_id', auth()->user()->company_id)->Orwhere('company_id', NULL)->orderBy('created_at', 'DESC')->get();
        return ok(__('Note Type'), $noteType);
    }
    /* Store note type */
    public function storeNoteType(Request $request)
    {
        $this->validate($request, [
            'name'          => 'required|max:64|unique:note_types,name,NULL,id,deleted_at,NULL',
            'contact_id'    => 'required|exists:contacts,id',

        ]);

        $request['icon_url']   = 'common.svg';
        $request['company_id'] = auth()->user()->company_id;
        $noteType = NoteType::create($request->only('name', 'icon_url', 'company_id'));

        $protocolmessage = auth()->user()->first_name . ' ' . auth()->user()->last_name . ' has created a new note type';
        $this->addProtocol($request->contact_id, 'notes', $protocolmessage);

        return ok(__('Note type stored successfully'), $noteType);
    }

    /*store notes */
    public function addNote(Request $request)
    {

        $this->validate($request, [
            'contact_id'   => 'required|exists:contacts,id',
            'subject'      => 'required|max:100',
            'note_type_id' => 'required|max:64',
            'note_content' => 'required'
        ]);

        ini_set('memory_limit', '-1');
        /* Check contact valid or not */
        $contact = CompanyContact::where('id', $request->contact_id)->where('company_id', auth()->user()->company_id)->first();

        if (!$contact) {
            return error(__('Please select valid contact or company type'), [], 'validation');
        }
        /* Store dynamic note type and his image */

        $noteTypeQuery = NoteType::query();

        $noteTypeExist = (clone $noteTypeQuery)->where('id', $request->note_type_id)->exists();

        if (!$noteTypeExist) {
            $noteTypeName = (clone $noteTypeQuery)->where('name', $request->note_type_id)->exists();
            if ($noteTypeName) {
                return error(__('Note type already exists'), [], 'validation');
            }
            //$iconUrl             = uploadSvgFile();
            $request['name']       =  $request->note_type_id;
            $request['icon_url']   = 'common.svg';
            $request['company_id'] = auth()->user()->company_id;
            $noteType              = (clone $noteTypeQuery)->create($request->only('name', 'icon_url', 'company_id'));
            $noteTypeId            = $noteType->id;
        } else {
            $noteTypeId            = $request->note_type_id;
        }
        $request['sub_type'] = $contact->sub_type;
        $request['company_id'] = auth()->user()->company_id;
        $request['note_type_id'] = $noteTypeId;
        $note = Note::create($request->only('sub_type', 'contact_id', 'subject', 'note_type_id', 'note_content', 'company_id'));

        /*Sent notification when note create */
        if ($request->sub_type == "C") {
            $name = $contact->company_name;
        } else {
            $name = $contact->full_name;
        }
        $message = auth()->user()->first_name . ' ' . auth()->user()->last_name . ' ' . ' has added a new note to the ' . $name . "'" . 's profile';
        $type = 'note';

        $protocolmessage = auth()->user()->first_name . ' ' . auth()->user()->last_name . ' has added a note';
        $this->addProtocol($request->contact_id, 'notes', $protocolmessage);

        sentNotification($message, $type);
        return ok(__('Note created succesfully'), $note);
    }
    /* List of notes */
    public function listNote(Request $request)
    {
        $this->validate($request, [
            'contact_id' => 'required|exists:contacts,id',
            'page'      => 'nullable',
            'perPage'   => 'nullable',
            'note_type_id' => 'nullable',
        ]);

        ini_set('memory_limit', '-1');
        $noteQuery = Note::where('contact_id', $request->contact_id)->with('users:id,first_name,last_name', 'noteType:id,icon_url,name')->where('company_id', auth()->user()->company_id);

        if ($request->note_type_id) {
            $noteQuery = $noteQuery->whereHas('noteType', function ($q) use ($request) {
                $q->where('id', $request->note_type_id);
            });
        }
        $count = $noteQuery->count();
        /*Set Pagination */
        if ($request->page && $request->perPage) {
            $page           = $request->page;
            $perPage        = $request->perPage;
            $noteQuery      = $noteQuery->skip($perPage * ($page - 1))->take($perPage);
        }
        $notes = $noteQuery->orderBy('created_at', 'DESC')->get();
        return ok(__('Notes'), [
            'notes' => $notes,
            'count' => $count
        ]);
    }

    /* View of note */
    public function viewNote(Request $request)
    {
        $this->validate($request, [
            'note_id'   => 'required|exists:notes,id',
            'contact_id' => 'required|exists:contacts,id'
        ]);

        $note = Note::with('users:id,first_name,last_name', 'noteType:id,name,icon_url')->where('id', $request->note_id)->where('company_id', auth()->user()->company_id)->where('contact_id', $request->contact_id)->first();
        if (!$note) {
            return error(__('Note not found'), [], 'validation');
        }
        return ok(__('Note'), $note);
    }
    /*Edit note */
    public function editNote(Request $request)
    {
        $this->validate($request, [
            'note_id'      => 'required|exists:notes,id',
            'note_type_id' => 'required|max:64',
            'subject'      => 'nullable|max:100',
            'note_content' => 'required',
        ]);

        ini_set('memory_limit', '-1');

        $noteTypeQuery = NoteType::query();

        $noteTypeExist = (clone $noteTypeQuery)->where('id', $request->note_type_id)->exists();

        if (!$noteTypeExist) {
            $noteTypeName = (clone $noteTypeQuery)->where('name', $request->note_type_id)->exists();
            if ($noteTypeName) {
                return error(__('Note type already exists'), [], 'validation');
            }
            //$iconUrl             = uploadSvgFile();
            $request['name']     =  $request->note_type_id;
            $request['icon_url'] = 'common.svg';
            $noteType            = (clone $noteTypeQuery)->create($request->only('name', 'icon_url'));
            $noteTypeId          = $noteType->id;
        } else {
            $noteTypeId          = $request->note_type_id;
        }
        $note = Note::where('id', $request->note_id)->where('company_id', auth()->user()->company_id)->first();
        if (!$note) {
            return error(__('Note not found'), [], 'validation');
        }
        $request['note_type_id'] = $noteTypeId;
        $note->update($request->only('note_content', 'note_type_id', 'subject'));

        $protocolmessage = auth()->user()->first_name . ' ' . auth()->user()->last_name . ' has updated a note';
        $this->addProtocol($note->contact_id, 'notes', $protocolmessage);

        /* Sent notification when update note */
        $message = auth()->user()->first_name . ' ' . auth()->user()->last_name . ' has updated a note';
        $type    = 'note';
        sentNotification($message, $type);
        return ok(__('Note updated successfully'), $note);
    }
}
