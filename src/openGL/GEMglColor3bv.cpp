////////////////////////////////////////////////////////
//
// GEM - Graphics Environment for Multimedia
//
// Implementation file
//
// Copyright (c) 2002 IOhannes m zmoelnig. forum::für::umläute. IEM
//	zmoelnig@iem.kug.ac.at
//  For information on usage and redistribution, and for a DISCLAIMER
//  *  OF ALL WARRANTIES, see the file, "GEM.LICENSE.TERMS"
//
//  this file has been generated...
////////////////////////////////////////////////////////

#include "GEMglColor3bv.h"

CPPEXTERN_NEW_WITH_THREE_ARGS ( GEMglColor3bv , t_floatarg, A_DEFFLOAT, t_floatarg, A_DEFFLOAT, t_floatarg, A_DEFFLOAT);

/////////////////////////////////////////////////////////
//
// GEMglColor3bv
//
/////////////////////////////////////////////////////////
// Constructor
//
GEMglColor3bv :: GEMglColor3bv	(t_floatarg arg0=0, t_floatarg arg1=0, t_floatarg arg2=0) {
vMess(arg0, arg1, arg2);
	m_inlet = inlet_new(this->x_obj, &this->x_obj->ob_pd, &s_float, gensym("v"));
}
/////////////////////////////////////////////////////////
// Destructor
//
GEMglColor3bv :: ~GEMglColor3bv () {
	inlet_free(m_inlet);
}

/////////////////////////////////////////////////////////
// Render
//
void GEMglColor3bv :: render(GemState *state) {
	glColor3bv (v);
}

/////////////////////////////////////////////////////////
// variable
//
void GEMglColor3bv :: vMess (t_float arg0, t_float arg1, t_float arg2) {	// FUN
	v[0]=static_cast<GLbyte>(arg0);
	v[1]=static_cast<GLbyte>(arg1);
	v[2]=static_cast<GLbyte>(arg2);
	setModified();
}

/////////////////////////////////////////////////////////
// static member functions
//

void GEMglColor3bv :: obj_setupCallback(t_class *classPtr) {
	 class_addmethod(classPtr, reinterpret_cast<t_method>(&GEMglColor3bv::vMessCallback),  	gensym("v"), A_DEFFLOAT, A_DEFFLOAT, A_DEFFLOAT, A_NULL);
}

void GEMglColor3bv :: vMessCallback (void* data, t_floatarg arg0, t_floatarg arg1, t_floatarg arg2) {
	GetMyClass(data)->vMess ( arg0, arg1, arg2);
}