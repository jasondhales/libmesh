<?php $root=""; ?>
<?php require($root."navigation.php"); ?>
<html>
<head>
  <?php load_style($root); ?>
</head>
 
<body>
 
<?php make_navigation("ex15",$root)?>
 
<div class="content">
<a name="comments"></a> 
<div class = "comment">
<h1>Example 15 - Biharmonic Equation</h1>

<br><br>This example solves the Biharmonic equation on a square or cube,
using a Galerkin formulation with C1 elements approximating the
H^2_0 function space.
The initial mesh contains two TRI6, one QUAD9 or one HEX27
An input file named "ex15.in"
is provided which allows the user to set several parameters for
the solution so that the problem can be re-run without a
re-compile.  The solution technique employed is to have a
refinement loop with a linear solve inside followed by a
refinement of the grid and projection of the solution to the new grid
In the final loop iteration, there is no additional
refinement after the solve.  In the input file "ex15.in", the variable
"max_r_steps" controls the number of refinement steps, and
"max_r_level" controls the maximum element refinement level.


<br><br>LibMesh include files.
</div>

<div class ="fragment">
<pre>
        #include "mesh.h"
        #include "equation_systems.h"
        #include "linear_implicit_system.h"
        #include "gmv_io.h"
        #include "fe.h"
        #include "quadrature.h"
        #include "dense_matrix.h"
        #include "dense_vector.h"
        #include "sparse_matrix.h"
        #include "mesh_generation.h"
        #include "mesh_modification.h"
        #include "mesh_refinement.h"
        #include "error_vector.h"
        #include "fourth_error_estimators.h"
        #include "getpot.h"
        #include "exact_solution.h"
        #include "dof_map.h"
        #include "numeric_vector.h"
        #include "elem.h"
        
</pre>
</div>
<div class = "comment">
Function prototype.  This is the function that will assemble
the linear system for our Biharmonic problem.  Note that the
function will take the \p EquationSystems object and the
name of the system we are assembling as input.  From the
\p EquationSystems object we have acess to the \p Mesh and
other objects we might need.
</div>

<div class ="fragment">
<pre>
        void assemble_biharmonic(EquationSystems& es,
                              const std::string& system_name);
        
        
        Number zero_solution(const Point&,
        		     const Parameters&,   // parameters, not needed
        		     const std::string&,  // sys_name, not needed
        		     const std::string&)  // unk_name, not needed);
        { return 0; }
        
        Gradient zero_derivative(const Point&,
        		         const Parameters&,   // parameters, not needed
        		         const std::string&,  // sys_name, not needed
        		         const std::string&)  // unk_name, not needed);
        { return Gradient(); }
        
        Tensor zero_hessian(const Point&,
        		    const Parameters&,   // parameters, not needed
        		    const std::string&,  // sys_name, not needed
        		    const std::string&)  // unk_name, not needed);
        { return Tensor(); }
        
</pre>
</div>
<div class = "comment">
Prototypes for calculation of the exact solution.  Necessary
for setting boundary conditions.
</div>

<div class ="fragment">
<pre>
        Number exact_2D_solution(const Point& p,
        		         const Parameters&,   // parameters, not needed
        		         const std::string&,  // sys_name, not needed
        		         const std::string&); // unk_name, not needed);
        
        Number exact_3D_solution(const Point& p,
          const Parameters&, const std::string&, const std::string&);
        
</pre>
</div>
<div class = "comment">
Prototypes for calculation of the gradient of the exact solution.  
Necessary for setting boundary conditions in H^2_0 and testing
H^1 convergence of the solution
</div>

<div class ="fragment">
<pre>
        Gradient exact_2D_derivative(const Point& p,
          const Parameters&, const std::string&, const std::string&);
        
        Gradient exact_3D_derivative(const Point& p,
          const Parameters&, const std::string&, const std::string&);
        
        Tensor exact_2D_hessian(const Point& p,
          const Parameters&, const std::string&, const std::string&);
        
        Tensor exact_3D_hessian(const Point& p,
          const Parameters&, const std::string&, const std::string&);
        
        Number forcing_function_2D(const Point& p);
        
        Number forcing_function_3D(const Point& p);
        
</pre>
</div>
<div class = "comment">
Pointers to dimension-independent functions
</div>

<div class ="fragment">
<pre>
        Number (*exact_solution)(const Point& p,
          const Parameters&, const std::string&, const std::string&);
        Gradient (*exact_derivative)(const Point& p,
          const Parameters&, const std::string&, const std::string&);
        Tensor (*exact_hessian)(const Point& p,
          const Parameters&, const std::string&, const std::string&);
        Number (*forcing_function)(const Point& p);
        
        
        
        int main(int argc, char** argv)
        {
</pre>
</div>
<div class = "comment">
Initialize libMesh.
</div>

<div class ="fragment">
<pre>
          libMesh::init (argc, argv);
        
        #ifndef ENABLE_SECOND_DERIVATIVES
        
          std::cerr &lt;&lt; "ERROR: This example requires the library to be "
        	    &lt;&lt; "compiled with second derivatives support!"
        	    &lt;&lt; std::endl;
          here();
        
          return 0;
        
        #else
        
          {
            
</pre>
</div>
<div class = "comment">
Parse the input file
</div>

<div class ="fragment">
<pre>
            GetPot input_file("ex15.in");
        
</pre>
</div>
<div class = "comment">
Read in parameters from the input file
</div>

<div class ="fragment">
<pre>
            const unsigned int max_r_level = input_file("max_r_level", 10);
            const unsigned int max_r_steps = input_file("max_r_steps", 4);
            const std::string approx_type  = input_file("approx_type",
        						"HERMITE");
            const unsigned int uniform_refine =
        		    input_file("uniform_refine", 0);
            const Real refine_percentage =
        		    input_file("refine_percentage", 0.5);
            const Real coarsen_percentage =
        		    input_file("coarsen_percentage", 0.5);
            const unsigned int dim =
        		    input_file("dimension", 2);
            const unsigned int max_linear_iterations =
        		    input_file("max_linear_iterations", 10000);
        
</pre>
</div>
<div class = "comment">
We have only defined 2 and 3 dimensional problems
</div>

<div class ="fragment">
<pre>
            assert (dim == 2 || dim == 3);
        
</pre>
</div>
<div class = "comment">
Currently only the Hermite cubics give a 3D C^1 basis
</div>

<div class ="fragment">
<pre>
            assert (dim == 2 || approx_type == "HERMITE");
        
</pre>
</div>
<div class = "comment">
Create a dim-dimensional mesh.
</div>

<div class ="fragment">
<pre>
            Mesh mesh (dim);
            
</pre>
</div>
<div class = "comment">
Output file for plotting the error 
</div>

<div class ="fragment">
<pre>
            std::string output_file = "";
        
            if (dim == 2)
              output_file += "2D_";
            else if (dim == 3)
              output_file += "3D_";
        
            if (approx_type == "HERMITE")
              output_file += "hermite_";
            else if (approx_type == "SECOND")
              output_file += "reducedclough_";
            else
              output_file += "clough_";
        
            if (uniform_refine == 0)
              output_file += "adaptive";
            else
              output_file += "uniform";
        
            std::string gmv_file = output_file;
            gmv_file += ".gmv";
            output_file += ".m";
        
            std::ofstream out (output_file.c_str());
            out &lt;&lt; "% dofs     L2-error     H1-error\n"
        	&lt;&lt; "e = [\n";
            
</pre>
</div>
<div class = "comment">
Set up the dimension-dependent coarse mesh and solution
</div>

<div class ="fragment">
<pre>
            if (dim == 2)
              {
                MeshTools::Generation::build_square(mesh, 1, 1);
                exact_solution = &exact_2D_solution;
                exact_derivative = &exact_2D_derivative;
                exact_hessian = &exact_2D_hessian;
                forcing_function = &forcing_function_2D;
              }
            else if (dim == 3)
              {
                MeshTools::Generation::build_cube(mesh, 1, 1, 1);
                exact_solution = &exact_3D_solution;
                exact_derivative = &exact_3D_derivative;
                exact_hessian = &exact_3D_hessian;
                forcing_function = &forcing_function_3D;
              }
        
</pre>
</div>
<div class = "comment">
Convert the mesh to second order: necessary for computing with
Clough-Tocher elements, useful for getting slightly less 
broken gmv output with Hermite elements
</div>

<div class ="fragment">
<pre>
            mesh.all_second_order();
        
</pre>
</div>
<div class = "comment">
Convert it to triangles if necessary
</div>

<div class ="fragment">
<pre>
            if (approx_type != "HERMITE")
              MeshTools::Modification::all_tri(mesh);
        
</pre>
</div>
<div class = "comment">
Mesh Refinement object
</div>

<div class ="fragment">
<pre>
            MeshRefinement mesh_refinement(mesh);
        
</pre>
</div>
<div class = "comment">
Create an equation systems object.
</div>

<div class ="fragment">
<pre>
            EquationSystems equation_systems (mesh);
        
</pre>
</div>
<div class = "comment">
Declare the system and its variables.
</div>

<div class ="fragment">
<pre>
            {
</pre>
</div>
<div class = "comment">
Creates a system named "Biharmonic"
</div>

<div class ="fragment">
<pre>
              LinearImplicitSystem& system =
        	equation_systems.add_system&lt;LinearImplicitSystem&gt; ("Biharmonic");
        
</pre>
</div>
<div class = "comment">
Adds the variable "u" to "Biharmonic".  "u"
will be approximated using Hermite tensor product squares
or (possibly reduced) cubic Clough-Tocher triangles
</div>

<div class ="fragment">
<pre>
              if (approx_type == "HERMITE")
                system.add_variable("u", THIRD, HERMITE);
              else if (approx_type == "SECOND")
                system.add_variable("u", SECOND, CLOUGH);
              else if (approx_type == "CLOUGH")
                system.add_variable("u", THIRD, CLOUGH);
              else
                error();
        
</pre>
</div>
<div class = "comment">
Give the system a pointer to the matrix assembly
function.
</div>

<div class ="fragment">
<pre>
              system.attach_assemble_function
        		      (assemble_biharmonic);
              
</pre>
</div>
<div class = "comment">
Initialize the data structures for the equation system.
</div>

<div class ="fragment">
<pre>
              equation_systems.init();
        
</pre>
</div>
<div class = "comment">
Set linear solver max iterations
</div>

<div class ="fragment">
<pre>
              equation_systems.parameters.set&lt;unsigned int&gt;
        		      ("linear solver maximum iterations") =
                              max_linear_iterations;
        
</pre>
</div>
<div class = "comment">
Linear solver tolerance.
</div>

<div class ="fragment">
<pre>
              equation_systems.parameters.set&lt;Real&gt;
        		      ("linear solver tolerance") = TOLERANCE *
                                                            TOLERANCE * TOLERANCE;
              
</pre>
</div>
<div class = "comment">
Prints information about the system to the screen.
</div>

<div class ="fragment">
<pre>
              equation_systems.print_info();
            }
        
</pre>
</div>
<div class = "comment">
Construct ExactSolution object and attach function to compute exact solution
</div>

<div class ="fragment">
<pre>
            ExactSolution exact_sol(equation_systems);
            exact_sol.attach_exact_value(exact_solution);
            exact_sol.attach_exact_deriv(exact_derivative);
            exact_sol.attach_exact_hessian(exact_hessian);
        
</pre>
</div>
<div class = "comment">
Construct zero solution object, useful for computing solution norms
</div>

<div class ="fragment">
<pre>
            ExactSolution zero_sol(equation_systems);
            zero_sol.attach_exact_value(zero_solution);
            zero_sol.attach_exact_deriv(zero_derivative);
            zero_sol.attach_exact_hessian(zero_hessian);
        
</pre>
</div>
<div class = "comment">
Convenient reference to the system
</div>

<div class ="fragment">
<pre>
            LinearImplicitSystem& system = 
              equation_systems.get_system&lt;LinearImplicitSystem&gt;("Biharmonic");
        
</pre>
</div>
<div class = "comment">
A refinement loop.
</div>

<div class ="fragment">
<pre>
            for (unsigned int r_step=0; r_step&lt;max_r_steps; r_step++)
              {
                mesh.print_info();
                equation_systems.print_info();
        
        	std::cout &lt;&lt; "Beginning Solve " &lt;&lt; r_step &lt;&lt; std::endl;
        	
</pre>
</div>
<div class = "comment">
Solve the system "Biharmonic", just like example 2.
</div>

<div class ="fragment">
<pre>
                system.solve();
        
        	std::cout &lt;&lt; "Linear solver converged at step: "
        		  &lt;&lt; system.n_linear_iterations()
        		  &lt;&lt; ", final residual: "
        		  &lt;&lt; system.final_linear_residual()
        		  &lt;&lt; std::endl;
        
</pre>
</div>
<div class = "comment">
Compute the error.
</div>

<div class ="fragment">
<pre>
                exact_sol.compute_error("Biharmonic", "u");
</pre>
</div>
<div class = "comment">
Compute the norm.
</div>

<div class ="fragment">
<pre>
                zero_sol.compute_error("Biharmonic", "u");
        
</pre>
</div>
<div class = "comment">
Print out the error values
</div>

<div class ="fragment">
<pre>
                std::cout &lt;&lt; "L2-Norm is: "
        		  &lt;&lt; zero_sol.l2_error("Biharmonic", "u")
        		  &lt;&lt; std::endl;
        	std::cout &lt;&lt; "H1-Norm is: "
        		  &lt;&lt; zero_sol.h1_error("Biharmonic", "u")
        		  &lt;&lt; std::endl;
        	std::cout &lt;&lt; "H2-Norm is: "
        		  &lt;&lt; zero_sol.h2_error("Biharmonic", "u")
        		  &lt;&lt; std::endl
        		  &lt;&lt; std::endl;
        	std::cout &lt;&lt; "L2-Error is: "
        		  &lt;&lt; exact_sol.l2_error("Biharmonic", "u")
        		  &lt;&lt; std::endl;
        	std::cout &lt;&lt; "H1-Error is: "
        		  &lt;&lt; exact_sol.h1_error("Biharmonic", "u")
        		  &lt;&lt; std::endl;
        	std::cout &lt;&lt; "H2-Error is: "
        		  &lt;&lt; exact_sol.h2_error("Biharmonic", "u")
        		  &lt;&lt; std::endl
        		  &lt;&lt; std::endl;
        
</pre>
</div>
<div class = "comment">
Print to output file
</div>

<div class ="fragment">
<pre>
                out &lt;&lt; equation_systems.n_active_dofs() &lt;&lt; " "
        	    &lt;&lt; exact_sol.l2_error("Biharmonic", "u") &lt;&lt; " "
        	    &lt;&lt; exact_sol.h1_error("Biharmonic", "u") &lt;&lt; " "
        	    &lt;&lt; exact_sol.h2_error("Biharmonic", "u") &lt;&lt; std::endl;
        
</pre>
</div>
<div class = "comment">
Possibly refine the mesh
</div>

<div class ="fragment">
<pre>
                if (r_step+1 != max_r_steps)
        	  {
        	    std::cout &lt;&lt; "  Refining the mesh..." &lt;&lt; std::endl;
        
        	    if (uniform_refine == 0)
        	      {
        		ErrorVector error;
        		LaplacianErrorEstimator error_estimator;
        
        		error_estimator.estimate_error(system, error);
                        mesh_refinement.flag_elements_by_elem_fraction
        				(error, refine_percentage,
        				 coarsen_percentage, max_r_level);
        
        		std::cerr &lt;&lt; "Mean Error: " &lt;&lt; error.mean() &lt;&lt;
        				std::endl;
        		std::cerr &lt;&lt; "Error Variance: " &lt;&lt; error.variance() &lt;&lt;
        				std::endl;
        
        		mesh_refinement.refine_and_coarsen_elements();
                      }
        	    else
        	      {
                        mesh_refinement.uniformly_refine(1);
                      }
        		
</pre>
</div>
<div class = "comment">
This call reinitializes the \p EquationSystems object for
the newly refined mesh.  One of the steps in the
reinitialization is projecting the \p solution,
\p old_solution, etc... vectors from the old mesh to
the current one.
</div>

<div class ="fragment">
<pre>
                    equation_systems.reinit ();
        	  }
              }	    
            
</pre>
</div>
<div class = "comment">
Write out the solution
After solving the system write the solution
to a GMV-formatted plot file.
</div>

<div class ="fragment">
<pre>
            GMVIO (mesh).write_equation_systems (gmv_file,
            					 equation_systems);
</pre>
</div>
<div class = "comment">
Close up the output file.
</div>

<div class ="fragment">
<pre>
            out &lt;&lt; "];\n"
        	&lt;&lt; "hold on\n"
        	&lt;&lt; "loglog(e(:,1), e(:,2), 'bo-');\n"
        	&lt;&lt; "loglog(e(:,1), e(:,3), 'ro-');\n"
        	&lt;&lt; "loglog(e(:,1), e(:,4), 'go-');\n"
        	&lt;&lt; "xlabel('log(dofs)');\n"
        	&lt;&lt; "ylabel('log(error)');\n"
        	&lt;&lt; "title('C1 " &lt;&lt; approx_type &lt;&lt; " elements');\n"
        	&lt;&lt; "legend('L2-error', 'H1-error', 'H2-error');\n";
          }
          
</pre>
</div>
<div class = "comment">
All done.  
</div>

<div class ="fragment">
<pre>
          return libMesh::close ();
        #endif
        }
        
        
        
        Number exact_2D_solution(const Point& p,
        		         const Parameters&,  // parameters, not needed
        		         const std::string&, // sys_name, not needed
        		         const std::string&) // unk_name, not needed
        {
</pre>
</div>
<div class = "comment">
x and y coordinates in space
</div>

<div class ="fragment">
<pre>
          const Real x = p(0);
          const Real y = p(1);
        
</pre>
</div>
<div class = "comment">
analytic solution value
</div>

<div class ="fragment">
<pre>
          return 256.*(x-x*x)*(x-x*x)*(y-y*y)*(y-y*y);
        }
        
        
</pre>
</div>
<div class = "comment">
We now define the gradient of the exact solution
</div>

<div class ="fragment">
<pre>
        Gradient exact_2D_derivative(const Point& p,
        			     const Parameters&,  // parameters, not needed
        			     const std::string&, // sys_name, not needed
        			     const std::string&) // unk_name, not needed
        {
</pre>
</div>
<div class = "comment">
x and y coordinates in space
</div>

<div class ="fragment">
<pre>
          const Real x = p(0);
          const Real y = p(1);
        
</pre>
</div>
<div class = "comment">
First derivatives to be returned.
</div>

<div class ="fragment">
<pre>
          Gradient gradu;
        
          gradu(0) = 256.*2.*(x-x*x)*(1-2*x)*(y-y*y)*(y-y*y);
          gradu(1) = 256.*2.*(x-x*x)*(x-x*x)*(y-y*y)*(1-2*y);
        
          return gradu;
        }
        
        
</pre>
</div>
<div class = "comment">
We now define the hessian of the exact solution
</div>

<div class ="fragment">
<pre>
        Tensor exact_2D_hessian(const Point& p,
        			const Parameters&,  // parameters, not needed
        			const std::string&, // sys_name, not needed
        			const std::string&) // unk_name, not needed
        {
</pre>
</div>
<div class = "comment">
Second derivatives to be returned.
</div>

<div class ="fragment">
<pre>
          Tensor hessu;
          
</pre>
</div>
<div class = "comment">
x and y coordinates in space
</div>

<div class ="fragment">
<pre>
          const Real x = p(0);
          const Real y = p(1);
        
          hessu(0,0) = 256.*2.*(1-6.*x+6.*x*x)*(y-y*y)*(y-y*y);
          hessu(0,1) = 256.*4.*(x-x*x)*(1.-2.*x)*(y-y*y)*(1.-2.*y);
          hessu(1,1) = 256.*2.*(x-x*x)*(x-x*x)*(1.-6.*y+6.*y*y);
        
</pre>
</div>
<div class = "comment">
Hessians are always symmetric
</div>

<div class ="fragment">
<pre>
          hessu(1,0) = hessu(0,1);
          return hessu;
        }
        
        
        
        Number forcing_function_2D(const Point& p)
        {
</pre>
</div>
<div class = "comment">
x and y coordinates in space
</div>

<div class ="fragment">
<pre>
          const Real x = p(0);
          const Real y = p(1);
        
</pre>
</div>
<div class = "comment">
Equals laplacian(laplacian(u))
</div>

<div class ="fragment">
<pre>
          return 256. * 8. * (3.*((y-y*y)*(y-y*y)+(x-x*x)*(x-x*x))
                 + (1.-6.*x+6.*x*x)*(1.-6.*y+6.*y*y));
        }
        
        
        
        Number exact_3D_solution(const Point& p,
        		         const Parameters&,  // parameters, not needed
        		         const std::string&, // sys_name, not needed
        		         const std::string&) // unk_name, not needed
        {
</pre>
</div>
<div class = "comment">
xyz coordinates in space
</div>

<div class ="fragment">
<pre>
          const Real x = p(0);
          const Real y = p(1);
          const Real z = p(2);
          
</pre>
</div>
<div class = "comment">
analytic solution value
</div>

<div class ="fragment">
<pre>
          return 4096.*(x-x*x)*(x-x*x)*(y-y*y)*(y-y*y)*(z-z*z)*(z-z*z);
        }
        
        
        Gradient exact_3D_derivative(const Point& p,
        			     const Parameters&,  // parameters, not needed
        			     const std::string&, // sys_name, not needed
        			     const std::string&) // unk_name, not needed
        {
</pre>
</div>
<div class = "comment">
First derivatives to be returned.
</div>

<div class ="fragment">
<pre>
          Gradient gradu;
          
</pre>
</div>
<div class = "comment">
xyz coordinates in space
</div>

<div class ="fragment">
<pre>
          const Real x = p(0);
          const Real y = p(1);
          const Real z = p(2);
        
          gradu(0) = 4096.*2.*(x-x*x)*(1.-2.*x)*(y-y*y)*(y-y*y)*(z-z*z)*(z-z*z);
          gradu(1) = 4096.*2.*(x-x*x)*(x-x*x)*(y-y*y)*(1.-2.*y)*(z-z*z)*(z-z*z);
          gradu(2) = 4096.*2.*(x-x*x)*(x-x*x)*(y-y*y)*(y-y*y)*(z-z*z)*(1.-2.*z);
        
          return gradu;
        }
        
        
</pre>
</div>
<div class = "comment">
We now define the hessian of the exact solution
</div>

<div class ="fragment">
<pre>
        Tensor exact_3D_hessian(const Point& p,
        			const Parameters&,  // parameters, not needed
        			const std::string&, // sys_name, not needed
        			const std::string&) // unk_name, not needed
        {
</pre>
</div>
<div class = "comment">
Second derivatives to be returned.
</div>

<div class ="fragment">
<pre>
          Tensor hessu;
          
</pre>
</div>
<div class = "comment">
xyz coordinates in space
</div>

<div class ="fragment">
<pre>
          const Real x = p(0);
          const Real y = p(1);
          const Real z = p(2);
        
          hessu(0,0) = 4096.*(2.-12.*x+12.*x*x)*(y-y*y)*(y-y*y)*(z-z*z)*(z-z*z);
          hessu(0,1) = 4096.*4.*(x-x*x)*(1.-2.*x)*(y-y*y)*(1.-2.*y)*(z-z*z)*(z-z*z);
          hessu(0,2) = 4096.*4.*(x-x*x)*(1.-2.*x)*(y-y*y)*(y-y*y)*(z-z*z)*(1.-2.*z);
          hessu(1,1) = 4096.*(x-x*x)*(x-x*x)*(2.-12.*y+12.*y*y)*(z-z*z)*(z-z*z);
          hessu(1,2) = 4096.*4.*(x-x*x)*(x-x*x)*(y-y*y)*(1.-2.*y)*(z-z*z)*(1.-2.*z);
          hessu(2,2) = 4096.*(x-x*x)*(x-x*x)*(y-y*y)*(y-y*y)*(2.-12.*z+12.*z*z);
        
</pre>
</div>
<div class = "comment">
Hessians are always symmetric
</div>

<div class ="fragment">
<pre>
          hessu(1,0) = hessu(0,1);
          hessu(2,0) = hessu(0,2);
          hessu(2,1) = hessu(1,2);
        
          return hessu;
        }
        
        
        
        Number forcing_function_3D(const Point& p)
        {
</pre>
</div>
<div class = "comment">
xyz coordinates in space
</div>

<div class ="fragment">
<pre>
          const Real x = p(0);
          const Real y = p(1);
          const Real z = p(2);
        
</pre>
</div>
<div class = "comment">
Equals laplacian(laplacian(u))
</div>

<div class ="fragment">
<pre>
          return 4096. * 8. * (3.*((y-y*y)*(y-y*y)*(x-x*x)*(x-x*x) +
                                   (z-z*z)*(z-z*z)*(x-x*x)*(x-x*x) +
                                   (z-z*z)*(z-z*z)*(y-y*y)*(y-y*y)) +
                 (1.-6.*x+6.*x*x)*(1.-6.*y+6.*y*y)*(z-z*z)*(z-z*z) +
                 (1.-6.*x+6.*x*x)*(1.-6.*z+6.*z*z)*(y-y*y)*(y-y*y) +
                 (1.-6.*y+6.*y*y)*(1.-6.*z+6.*z*z)*(x-x*x)*(x-x*x));
        }
        
        
        
</pre>
</div>
<div class = "comment">
We now define the matrix assembly function for the
Biharmonic system.  We need to first compute element
matrices and right-hand sides, and then take into
account the boundary conditions, which will be handled
via a penalty method.
</div>

<div class ="fragment">
<pre>
        void assemble_biharmonic(EquationSystems& es,
                              const std::string& system_name)
        {
</pre>
</div>
<div class = "comment">
It is a good idea to make sure we are assembling
the proper system.
</div>

<div class ="fragment">
<pre>
          assert (system_name == "Biharmonic");
        
        #ifdef ENABLE_SECOND_DERIVATIVES
        
</pre>
</div>
<div class = "comment">
Declare a performance log.  Give it a descriptive
string to identify what part of the code we are
logging, since there may be many PerfLogs in an
application.
</div>

<div class ="fragment">
<pre>
          PerfLog perf_log ("Matrix Assembly",false);
          
</pre>
</div>
<div class = "comment">
Get a constant reference to the mesh object.
</div>

<div class ="fragment">
<pre>
          const Mesh& mesh = es.get_mesh();
        
</pre>
</div>
<div class = "comment">
The dimension that we are running
</div>

<div class ="fragment">
<pre>
          const unsigned int dim = mesh.mesh_dimension();
        
</pre>
</div>
<div class = "comment">
Get a reference to the LinearImplicitSystem we are solving
</div>

<div class ="fragment">
<pre>
          LinearImplicitSystem& system = es.get_system&lt;LinearImplicitSystem&gt;("Biharmonic");
          
</pre>
</div>
<div class = "comment">
A reference to the \p DofMap object for this system.  The \p DofMap
object handles the index translation from node and element numbers
to degree of freedom numbers.  We will talk more about the \p DofMap
in future examples.
</div>

<div class ="fragment">
<pre>
          const DofMap& dof_map = system.get_dof_map();
        
</pre>
</div>
<div class = "comment">
Get a constant reference to the Finite Element type
for the first (and only) variable in the system.
</div>

<div class ="fragment">
<pre>
          FEType fe_type = dof_map.variable_type(0);
        
</pre>
</div>
<div class = "comment">
Build a Finite Element object of the specified type.  Since the
\p FEBase::build() member dynamically creates memory we will
store the object as an \p AutoPtr<FEBase>.  This can be thought
of as a pointer that will clean up after itself.
</div>

<div class ="fragment">
<pre>
          AutoPtr&lt;FEBase&gt; fe (FEBase::build(dim, fe_type));
          
</pre>
</div>
<div class = "comment">
Quadrature rule for numerical integration.
With 2D triangles, the Clough quadrature rule puts a Gaussian
quadrature rule on each of the 3 subelements
</div>

<div class ="fragment">
<pre>
          AutoPtr&lt;QBase&gt; qrule(fe_type.default_quadrature_rule(dim));
        
</pre>
</div>
<div class = "comment">
Tell the finite element object to use our quadrature rule.
</div>

<div class ="fragment">
<pre>
          fe-&gt;attach_quadrature_rule (qrule.get());
        
</pre>
</div>
<div class = "comment">
Declare a special finite element object for
boundary integration.
</div>

<div class ="fragment">
<pre>
          AutoPtr&lt;FEBase&gt; fe_face (FEBase::build(dim, fe_type));
        	      
</pre>
</div>
<div class = "comment">
Boundary integration requires another quadraure rule,
with dimensionality one less than the dimensionality
of the element.
In 1D, the Clough and Gauss quadrature rules are identical.
</div>

<div class ="fragment">
<pre>
          AutoPtr&lt;QBase&gt; qface(fe_type.default_quadrature_rule(dim-1));
        
</pre>
</div>
<div class = "comment">
Tell the finte element object to use our
quadrature rule.
</div>

<div class ="fragment">
<pre>
          fe_face-&gt;attach_quadrature_rule (qface.get());
        
</pre>
</div>
<div class = "comment">
Here we define some references to cell-specific data that
will be used to assemble the linear system.
We begin with the element Jacobian * quadrature weight at each
integration point.   
</div>

<div class ="fragment">
<pre>
          const std::vector&lt;Real&gt;& JxW = fe-&gt;get_JxW();
        
</pre>
</div>
<div class = "comment">
The physical XY locations of the quadrature points on the element.
These might be useful for evaluating spatially varying material
properties at the quadrature points.
</div>

<div class ="fragment">
<pre>
          const std::vector&lt;Point&gt;& q_point = fe-&gt;get_xyz();
        
</pre>
</div>
<div class = "comment">
The element shape functions evaluated at the quadrature points.
</div>

<div class ="fragment">
<pre>
          const std::vector&lt;std::vector&lt;Real&gt; &gt;& phi = fe-&gt;get_phi();
        
</pre>
</div>
<div class = "comment">
The element shape function second derivatives evaluated at the
quadrature points.  Note that for the simple biharmonic, shape
function first derivatives are unnecessary.
</div>

<div class ="fragment">
<pre>
          const std::vector&lt;std::vector&lt;RealTensor&gt; &gt;& d2phi = fe-&gt;get_d2phi();
        
</pre>
</div>
<div class = "comment">
For efficiency we will compute shape function laplacians n times,
not n^2
</div>

<div class ="fragment">
<pre>
          std::vector&lt;Real&gt; shape_laplacian;
        
</pre>
</div>
<div class = "comment">
Define data structures to contain the element matrix
and right-hand-side vector contribution.  Following
basic finite element terminology we will denote these
"Ke" and "Fe". More detail is in example 3.
</div>

<div class ="fragment">
<pre>
          DenseMatrix&lt;Number&gt; Ke;
          DenseVector&lt;Number&gt; Fe;
        
</pre>
</div>
<div class = "comment">
This vector will hold the degree of freedom indices for
the element.  These define where in the global system
the element degrees of freedom get mapped.
</div>

<div class ="fragment">
<pre>
          std::vector&lt;unsigned int&gt; dof_indices;
        
</pre>
</div>
<div class = "comment">
Now we will loop over all the elements in the mesh.  We will
compute the element matrix and right-hand-side contribution.  See
example 3 for a discussion of the element iterators.


<br><br></div>

<div class ="fragment">
<pre>
          MeshBase::const_element_iterator       el     = mesh.active_local_elements_begin();
          const MeshBase::const_element_iterator end_el = mesh.active_local_elements_end(); 
          
          for ( ; el != end_el; ++el)
            {
</pre>
</div>
<div class = "comment">
Start logging the shape function initialization.
This is done through a simple function call with
the name of the event to log.
</div>

<div class ="fragment">
<pre>
              perf_log.start_event("elem init");      
        
</pre>
</div>
<div class = "comment">
Store a pointer to the element we are currently
working on.  This allows for nicer syntax later.
</div>

<div class ="fragment">
<pre>
              const Elem* elem = *el;
        
</pre>
</div>
<div class = "comment">
Get the degree of freedom indices for the
current element.  These define where in the global
matrix and right-hand-side this element will
contribute to.
</div>

<div class ="fragment">
<pre>
              dof_map.dof_indices (elem, dof_indices);
        
</pre>
</div>
<div class = "comment">
Compute the element-specific data for the current
element.  This involves computing the location of the
quadrature points (q_point) and the shape functions
(phi, dphi) for the current element.
</div>

<div class ="fragment">
<pre>
              fe-&gt;reinit (elem);
        
</pre>
</div>
<div class = "comment">
Zero the element matrix and right-hand side before
summing them.
</div>

<div class ="fragment">
<pre>
              Ke.resize (dof_indices.size(),
        		 dof_indices.size());
        
              Fe.resize (dof_indices.size());
        
</pre>
</div>
<div class = "comment">
Make sure there is enough room in this cache
</div>

<div class ="fragment">
<pre>
              shape_laplacian.resize(dof_indices.size());
        
</pre>
</div>
<div class = "comment">
Stop logging the shape function initialization.
If you forget to stop logging an event the PerfLog
object will probably catch the error and abort.
</div>

<div class ="fragment">
<pre>
              perf_log.stop_event("elem init");      
        
</pre>
</div>
<div class = "comment">
Now we will build the element matrix.  This involves
a double loop to integrate laplacians of the test funcions
(i) against laplacians of the trial functions (j).

<br><br>This step is why we need the Clough-Tocher elements -
these C1 differentiable elements have square-integrable
second derivatives.

<br><br>Now start logging the element matrix computation
</div>

<div class ="fragment">
<pre>
              perf_log.start_event ("Ke");
        
              for (unsigned int qp=0; qp&lt;qrule-&gt;n_points(); qp++)
                {
        	  for (unsigned int i=0; i&lt;phi.size(); i++)
                    {
                      shape_laplacian[i] = d2phi[i][qp](0,0)+d2phi[i][qp](1,1);
                      if (dim == 3)
                         shape_laplacian[i] += d2phi[i][qp](2,2);
                    }
        	  for (unsigned int i=0; i&lt;phi.size(); i++)
        	    for (unsigned int j=0; j&lt;phi.size(); j++)
        	      Ke(i,j) += JxW[qp]*
                                 shape_laplacian[i]*shape_laplacian[j];
                }
        
</pre>
</div>
<div class = "comment">
Stop logging the matrix computation
</div>

<div class ="fragment">
<pre>
              perf_log.stop_event ("Ke");
        
        
</pre>
</div>
<div class = "comment">
At this point the interior element integration has
been completed.  However, we have not yet addressed
boundary conditions.  For this example we will only
consider simple Dirichlet boundary conditions imposed
via the penalty method.  Note that this is a fourth-order
problem: Dirichlet boundary conditions include *both*
boundary values and boundary normal fluxes.
</div>

<div class ="fragment">
<pre>
              {
</pre>
</div>
<div class = "comment">
Start logging the boundary condition computation
</div>

<div class ="fragment">
<pre>
                perf_log.start_event ("BCs");
        
</pre>
</div>
<div class = "comment">
The penalty values, for solution boundary trace and flux.  
</div>

<div class ="fragment">
<pre>
                const Real penalty = 1e10;
        	const Real penalty2 = 1e10;
        
</pre>
</div>
<div class = "comment">
The following loops over the sides of the element.
If the element has no neighbor on a side then that
side MUST live on a boundary of the domain.
</div>

<div class ="fragment">
<pre>
                for (unsigned int s=0; s&lt;elem-&gt;n_sides(); s++)
        	  if (elem-&gt;neighbor(s) == NULL)
        	    {
</pre>
</div>
<div class = "comment">
The value of the shape functions at the quadrature
points.
</div>

<div class ="fragment">
<pre>
                      const std::vector&lt;std::vector&lt;Real&gt; &gt;&  phi_face =
        			      fe_face-&gt;get_phi();
        
</pre>
</div>
<div class = "comment">
The value of the shape function derivatives at the
quadrature points.
</div>

<div class ="fragment">
<pre>
                      const std::vector&lt;std::vector&lt;RealGradient&gt; &gt;& dphi_face =
        			      fe_face-&gt;get_dphi();
        
</pre>
</div>
<div class = "comment">
The Jacobian * Quadrature Weight at the quadrature
points on the face.
</div>

<div class ="fragment">
<pre>
                      const std::vector&lt;Real&gt;& JxW_face = fe_face-&gt;get_JxW();
                                                                                       
</pre>
</div>
<div class = "comment">
The XYZ locations (in physical space) of the
quadrature points on the face.  This is where
we will interpolate the boundary value function.
</div>

<div class ="fragment">
<pre>
                      const std::vector&lt;Point &gt;& qface_point = fe_face-&gt;get_xyz();
        
        	      const std::vector&lt;Point&gt;& face_normals =
        			      fe_face-&gt;get_normals();
        
</pre>
</div>
<div class = "comment">
Compute the shape function values on the element
face.
</div>

<div class ="fragment">
<pre>
                      fe_face-&gt;reinit(elem, s);
                                                                                        
</pre>
</div>
<div class = "comment">
Loop over the face quagrature points for integration.
</div>

<div class ="fragment">
<pre>
                      for (unsigned int qp=0; qp&lt;qface-&gt;n_points(); qp++)
                        {
</pre>
</div>
<div class = "comment">
The boundary value.
</div>

<div class ="fragment">
<pre>
                          Number value = exact_solution(qface_point[qp],
        					        es.parameters, "null",
        					        "void");
        		  Gradient flux = exact_2D_derivative(qface_point[qp],
                                                              es.parameters,
        						      "null", "void");
        
</pre>
</div>
<div class = "comment">
Matrix contribution of the L2 projection.
Note that the basis function values are
integrated against test function values while
basis fluxes are integrated against test function
fluxes.
</div>

<div class ="fragment">
<pre>
                          for (unsigned int i=0; i&lt;phi_face.size(); i++)
                            for (unsigned int j=0; j&lt;phi_face.size(); j++)
        		      Ke(i,j) += JxW_face[qp] *
        				 (penalty * phi_face[i][qp] *
        				  phi_face[j][qp] + penalty2
        				  * (dphi_face[i][qp] *
        				  face_normals[qp]) *
        				  (dphi_face[j][qp] *
        				   face_normals[qp]));
        
</pre>
</div>
<div class = "comment">
Right-hand-side contribution of the L2
projection.
</div>

<div class ="fragment">
<pre>
                          for (unsigned int i=0; i&lt;phi_face.size(); i++)
                            Fe(i) += JxW_face[qp] *
        				    (penalty * value * phi_face[i][qp]
        				     + penalty2 * 
        				     (flux * face_normals[qp])
        				    * (dphi_face[i][qp]
        				       * face_normals[qp]));
        
                        }
        	    } 
        	
</pre>
</div>
<div class = "comment">
Stop logging the boundary condition computation
</div>

<div class ="fragment">
<pre>
                perf_log.stop_event ("BCs");
              } 
        
              for (unsigned int qp=0; qp&lt;qrule-&gt;n_points(); qp++)
        	for (unsigned int i=0; i&lt;phi.size(); i++)
        	  Fe(i) += JxW[qp]*phi[i][qp]*forcing_function(q_point[qp]);
        
</pre>
</div>
<div class = "comment">
The element matrix and right-hand-side are now built
for this element.  Add them to the global matrix and
right-hand-side vector.  The \p PetscMatrix::add_matrix()
and \p PetscVector::add_vector() members do this for us.
Start logging the insertion of the local (element)
matrix and vector into the global matrix and vector
</div>

<div class ="fragment">
<pre>
              perf_log.start_event ("matrix insertion");
        
              dof_map.constrain_element_matrix_and_vector(Ke, Fe, dof_indices);
              system.matrix-&gt;add_matrix (Ke, dof_indices);
              system.rhs-&gt;add_vector    (Fe, dof_indices);
        
</pre>
</div>
<div class = "comment">
Stop logging the insertion of the local (element)
matrix and vector into the global matrix and vector
</div>

<div class ="fragment">
<pre>
              perf_log.stop_event ("matrix insertion");
            }
        
</pre>
</div>
<div class = "comment">
That's it.  We don't need to do anything else to the
PerfLog.  When it goes out of scope (at this function return)
it will print its log to the screen. Pretty easy, huh?


<br><br></div>

<div class ="fragment">
<pre>
        #else
        
        #endif
        
        }
</pre>
</div>

<a name="nocomments"></a> 
<br><br><br> <h1> The program without comments: </h1> 
<pre> 
  
  #include <FONT COLOR="#BC8F8F"><B>&quot;mesh.h&quot;</FONT></B>
  #include <FONT COLOR="#BC8F8F"><B>&quot;equation_systems.h&quot;</FONT></B>
  #include <FONT COLOR="#BC8F8F"><B>&quot;linear_implicit_system.h&quot;</FONT></B>
  #include <FONT COLOR="#BC8F8F"><B>&quot;gmv_io.h&quot;</FONT></B>
  #include <FONT COLOR="#BC8F8F"><B>&quot;fe.h&quot;</FONT></B>
  #include <FONT COLOR="#BC8F8F"><B>&quot;quadrature.h&quot;</FONT></B>
  #include <FONT COLOR="#BC8F8F"><B>&quot;dense_matrix.h&quot;</FONT></B>
  #include <FONT COLOR="#BC8F8F"><B>&quot;dense_vector.h&quot;</FONT></B>
  #include <FONT COLOR="#BC8F8F"><B>&quot;sparse_matrix.h&quot;</FONT></B>
  #include <FONT COLOR="#BC8F8F"><B>&quot;mesh_generation.h&quot;</FONT></B>
  #include <FONT COLOR="#BC8F8F"><B>&quot;mesh_modification.h&quot;</FONT></B>
  #include <FONT COLOR="#BC8F8F"><B>&quot;mesh_refinement.h&quot;</FONT></B>
  #include <FONT COLOR="#BC8F8F"><B>&quot;error_vector.h&quot;</FONT></B>
  #include <FONT COLOR="#BC8F8F"><B>&quot;fourth_error_estimators.h&quot;</FONT></B>
  #include <FONT COLOR="#BC8F8F"><B>&quot;getpot.h&quot;</FONT></B>
  #include <FONT COLOR="#BC8F8F"><B>&quot;exact_solution.h&quot;</FONT></B>
  #include <FONT COLOR="#BC8F8F"><B>&quot;dof_map.h&quot;</FONT></B>
  #include <FONT COLOR="#BC8F8F"><B>&quot;numeric_vector.h&quot;</FONT></B>
  #include <FONT COLOR="#BC8F8F"><B>&quot;elem.h&quot;</FONT></B>
  
  <FONT COLOR="#228B22"><B>void</FONT></B> assemble_biharmonic(EquationSystems&amp; es,
                        <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp; system_name);
  
  
  Number zero_solution(<FONT COLOR="#228B22"><B>const</FONT></B> Point&amp;,
  		     <FONT COLOR="#228B22"><B>const</FONT></B> Parameters&amp;,   <I><FONT COLOR="#B22222">// parameters, not needed
</FONT></I>  		     <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;,  <I><FONT COLOR="#B22222">// sys_name, not needed
</FONT></I>  		     <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;)  <I><FONT COLOR="#B22222">// unk_name, not needed);
</FONT></I>  { <B><FONT COLOR="#A020F0">return</FONT></B> 0; }
  
  Gradient zero_derivative(<FONT COLOR="#228B22"><B>const</FONT></B> Point&amp;,
  		         <FONT COLOR="#228B22"><B>const</FONT></B> Parameters&amp;,   <I><FONT COLOR="#B22222">// parameters, not needed
</FONT></I>  		         <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;,  <I><FONT COLOR="#B22222">// sys_name, not needed
</FONT></I>  		         <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;)  <I><FONT COLOR="#B22222">// unk_name, not needed);
</FONT></I>  { <B><FONT COLOR="#A020F0">return</FONT></B> Gradient(); }
  
  Tensor zero_hessian(<FONT COLOR="#228B22"><B>const</FONT></B> Point&amp;,
  		    <FONT COLOR="#228B22"><B>const</FONT></B> Parameters&amp;,   <I><FONT COLOR="#B22222">// parameters, not needed
</FONT></I>  		    <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;,  <I><FONT COLOR="#B22222">// sys_name, not needed
</FONT></I>  		    <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;)  <I><FONT COLOR="#B22222">// unk_name, not needed);
</FONT></I>  { <B><FONT COLOR="#A020F0">return</FONT></B> Tensor(); }
  
  Number exact_2D_solution(<FONT COLOR="#228B22"><B>const</FONT></B> Point&amp; p,
  		         <FONT COLOR="#228B22"><B>const</FONT></B> Parameters&amp;,   <I><FONT COLOR="#B22222">// parameters, not needed
</FONT></I>  		         <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;,  <I><FONT COLOR="#B22222">// sys_name, not needed
</FONT></I>  		         <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;); <I><FONT COLOR="#B22222">// unk_name, not needed);
</FONT></I>  
  Number exact_3D_solution(<FONT COLOR="#228B22"><B>const</FONT></B> Point&amp; p,
    <FONT COLOR="#228B22"><B>const</FONT></B> Parameters&amp;, <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;, <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;);
  
  Gradient exact_2D_derivative(<FONT COLOR="#228B22"><B>const</FONT></B> Point&amp; p,
    <FONT COLOR="#228B22"><B>const</FONT></B> Parameters&amp;, <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;, <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;);
  
  Gradient exact_3D_derivative(<FONT COLOR="#228B22"><B>const</FONT></B> Point&amp; p,
    <FONT COLOR="#228B22"><B>const</FONT></B> Parameters&amp;, <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;, <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;);
  
  Tensor exact_2D_hessian(<FONT COLOR="#228B22"><B>const</FONT></B> Point&amp; p,
    <FONT COLOR="#228B22"><B>const</FONT></B> Parameters&amp;, <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;, <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;);
  
  Tensor exact_3D_hessian(<FONT COLOR="#228B22"><B>const</FONT></B> Point&amp; p,
    <FONT COLOR="#228B22"><B>const</FONT></B> Parameters&amp;, <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;, <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;);
  
  Number forcing_function_2D(<FONT COLOR="#228B22"><B>const</FONT></B> Point&amp; p);
  
  Number forcing_function_3D(<FONT COLOR="#228B22"><B>const</FONT></B> Point&amp; p);
  
  Number (*exact_solution)(<FONT COLOR="#228B22"><B>const</FONT></B> Point&amp; p,
    <FONT COLOR="#228B22"><B>const</FONT></B> Parameters&amp;, <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;, <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;);
  Gradient (*exact_derivative)(<FONT COLOR="#228B22"><B>const</FONT></B> Point&amp; p,
    <FONT COLOR="#228B22"><B>const</FONT></B> Parameters&amp;, <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;, <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;);
  Tensor (*exact_hessian)(<FONT COLOR="#228B22"><B>const</FONT></B> Point&amp; p,
    <FONT COLOR="#228B22"><B>const</FONT></B> Parameters&amp;, <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;, <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;);
  Number (*forcing_function)(<FONT COLOR="#228B22"><B>const</FONT></B> Point&amp; p);
  
  
  
  <FONT COLOR="#228B22"><B>int</FONT></B> main(<FONT COLOR="#228B22"><B>int</FONT></B> argc, <FONT COLOR="#228B22"><B>char</FONT></B>** argv)
  {
    libMesh::init (argc, argv);
  
  #ifndef ENABLE_SECOND_DERIVATIVES
  
    std::cerr &lt;&lt; <FONT COLOR="#BC8F8F"><B>&quot;ERROR: This example requires the library to be &quot;</FONT></B>
  	    &lt;&lt; <FONT COLOR="#BC8F8F"><B>&quot;compiled with second derivatives support!&quot;</FONT></B>
  	    &lt;&lt; std::endl;
    here();
  
    <B><FONT COLOR="#A020F0">return</FONT></B> 0;
  
  #<B><FONT COLOR="#A020F0">else</FONT></B>
  
    {
      
      GetPot input_file(<FONT COLOR="#BC8F8F"><B>&quot;ex15.in&quot;</FONT></B>);
  
      <FONT COLOR="#228B22"><B>const</FONT></B> <FONT COLOR="#228B22"><B>unsigned</FONT></B> <FONT COLOR="#228B22"><B>int</FONT></B> max_r_level = input_file(<FONT COLOR="#BC8F8F"><B>&quot;max_r_level&quot;</FONT></B>, 10);
      <FONT COLOR="#228B22"><B>const</FONT></B> <FONT COLOR="#228B22"><B>unsigned</FONT></B> <FONT COLOR="#228B22"><B>int</FONT></B> max_r_steps = input_file(<FONT COLOR="#BC8F8F"><B>&quot;max_r_steps&quot;</FONT></B>, 4);
      <FONT COLOR="#228B22"><B>const</FONT></B> std::string approx_type  = input_file(<FONT COLOR="#BC8F8F"><B>&quot;approx_type&quot;</FONT></B>,
  						<FONT COLOR="#BC8F8F"><B>&quot;HERMITE&quot;</FONT></B>);
      <FONT COLOR="#228B22"><B>const</FONT></B> <FONT COLOR="#228B22"><B>unsigned</FONT></B> <FONT COLOR="#228B22"><B>int</FONT></B> uniform_refine =
  		    input_file(<FONT COLOR="#BC8F8F"><B>&quot;uniform_refine&quot;</FONT></B>, 0);
      <FONT COLOR="#228B22"><B>const</FONT></B> Real refine_percentage =
  		    input_file(<FONT COLOR="#BC8F8F"><B>&quot;refine_percentage&quot;</FONT></B>, 0.5);
      <FONT COLOR="#228B22"><B>const</FONT></B> Real coarsen_percentage =
  		    input_file(<FONT COLOR="#BC8F8F"><B>&quot;coarsen_percentage&quot;</FONT></B>, 0.5);
      <FONT COLOR="#228B22"><B>const</FONT></B> <FONT COLOR="#228B22"><B>unsigned</FONT></B> <FONT COLOR="#228B22"><B>int</FONT></B> dim =
  		    input_file(<FONT COLOR="#BC8F8F"><B>&quot;dimension&quot;</FONT></B>, 2);
      <FONT COLOR="#228B22"><B>const</FONT></B> <FONT COLOR="#228B22"><B>unsigned</FONT></B> <FONT COLOR="#228B22"><B>int</FONT></B> max_linear_iterations =
  		    input_file(<FONT COLOR="#BC8F8F"><B>&quot;max_linear_iterations&quot;</FONT></B>, 10000);
  
      assert (dim == 2 || dim == 3);
  
      assert (dim == 2 || approx_type == <FONT COLOR="#BC8F8F"><B>&quot;HERMITE&quot;</FONT></B>);
  
      Mesh mesh (dim);
      
      std::string output_file = <FONT COLOR="#BC8F8F"><B>&quot;&quot;</FONT></B>;
  
      <B><FONT COLOR="#A020F0">if</FONT></B> (dim == 2)
        output_file += <FONT COLOR="#BC8F8F"><B>&quot;2D_&quot;</FONT></B>;
      <B><FONT COLOR="#A020F0">else</FONT></B> <B><FONT COLOR="#A020F0">if</FONT></B> (dim == 3)
        output_file += <FONT COLOR="#BC8F8F"><B>&quot;3D_&quot;</FONT></B>;
  
      <B><FONT COLOR="#A020F0">if</FONT></B> (approx_type == <FONT COLOR="#BC8F8F"><B>&quot;HERMITE&quot;</FONT></B>)
        output_file += <FONT COLOR="#BC8F8F"><B>&quot;hermite_&quot;</FONT></B>;
      <B><FONT COLOR="#A020F0">else</FONT></B> <B><FONT COLOR="#A020F0">if</FONT></B> (approx_type == <FONT COLOR="#BC8F8F"><B>&quot;SECOND&quot;</FONT></B>)
        output_file += <FONT COLOR="#BC8F8F"><B>&quot;reducedclough_&quot;</FONT></B>;
      <B><FONT COLOR="#A020F0">else</FONT></B>
        output_file += <FONT COLOR="#BC8F8F"><B>&quot;clough_&quot;</FONT></B>;
  
      <B><FONT COLOR="#A020F0">if</FONT></B> (uniform_refine == 0)
        output_file += <FONT COLOR="#BC8F8F"><B>&quot;adaptive&quot;</FONT></B>;
      <B><FONT COLOR="#A020F0">else</FONT></B>
        output_file += <FONT COLOR="#BC8F8F"><B>&quot;uniform&quot;</FONT></B>;
  
      std::string gmv_file = output_file;
      gmv_file += <FONT COLOR="#BC8F8F"><B>&quot;.gmv&quot;</FONT></B>;
      output_file += <FONT COLOR="#BC8F8F"><B>&quot;.m&quot;</FONT></B>;
  
      std::ofstream out (output_file.c_str());
      out &lt;&lt; <FONT COLOR="#BC8F8F"><B>&quot;% dofs     L2-error     H1-error\n&quot;</FONT></B>
  	&lt;&lt; <FONT COLOR="#BC8F8F"><B>&quot;e = [\n&quot;</FONT></B>;
      
      <B><FONT COLOR="#A020F0">if</FONT></B> (dim == 2)
        {
          MeshTools::Generation::build_square(mesh, 1, 1);
          exact_solution = &amp;exact_2D_solution;
          exact_derivative = &amp;exact_2D_derivative;
          exact_hessian = &amp;exact_2D_hessian;
          forcing_function = &amp;forcing_function_2D;
        }
      <B><FONT COLOR="#A020F0">else</FONT></B> <B><FONT COLOR="#A020F0">if</FONT></B> (dim == 3)
        {
          MeshTools::Generation::build_cube(mesh, 1, 1, 1);
          exact_solution = &amp;exact_3D_solution;
          exact_derivative = &amp;exact_3D_derivative;
          exact_hessian = &amp;exact_3D_hessian;
          forcing_function = &amp;forcing_function_3D;
        }
  
      mesh.all_second_order();
  
      <B><FONT COLOR="#A020F0">if</FONT></B> (approx_type != <FONT COLOR="#BC8F8F"><B>&quot;HERMITE&quot;</FONT></B>)
        MeshTools::Modification::all_tri(mesh);
  
      MeshRefinement mesh_refinement(mesh);
  
      EquationSystems equation_systems (mesh);
  
      {
        LinearImplicitSystem&amp; system =
  	equation_systems.add_system&lt;LinearImplicitSystem&gt; (<FONT COLOR="#BC8F8F"><B>&quot;Biharmonic&quot;</FONT></B>);
  
        <B><FONT COLOR="#A020F0">if</FONT></B> (approx_type == <FONT COLOR="#BC8F8F"><B>&quot;HERMITE&quot;</FONT></B>)
          system.add_variable(<FONT COLOR="#BC8F8F"><B>&quot;u&quot;</FONT></B>, THIRD, HERMITE);
        <B><FONT COLOR="#A020F0">else</FONT></B> <B><FONT COLOR="#A020F0">if</FONT></B> (approx_type == <FONT COLOR="#BC8F8F"><B>&quot;SECOND&quot;</FONT></B>)
          system.add_variable(<FONT COLOR="#BC8F8F"><B>&quot;u&quot;</FONT></B>, SECOND, CLOUGH);
        <B><FONT COLOR="#A020F0">else</FONT></B> <B><FONT COLOR="#A020F0">if</FONT></B> (approx_type == <FONT COLOR="#BC8F8F"><B>&quot;CLOUGH&quot;</FONT></B>)
          system.add_variable(<FONT COLOR="#BC8F8F"><B>&quot;u&quot;</FONT></B>, THIRD, CLOUGH);
        <B><FONT COLOR="#A020F0">else</FONT></B>
          error();
  
        system.attach_assemble_function
  		      (assemble_biharmonic);
        
        equation_systems.init();
  
        equation_systems.parameters.set&lt;<FONT COLOR="#228B22"><B>unsigned</FONT></B> <FONT COLOR="#228B22"><B>int</FONT></B>&gt;
  		      (<FONT COLOR="#BC8F8F"><B>&quot;linear solver maximum iterations&quot;</FONT></B>) =
                        max_linear_iterations;
  
        equation_systems.parameters.set&lt;Real&gt;
  		      (<FONT COLOR="#BC8F8F"><B>&quot;linear solver tolerance&quot;</FONT></B>) = TOLERANCE *
                                                      TOLERANCE * TOLERANCE;
        
        equation_systems.print_info();
      }
  
      ExactSolution exact_sol(equation_systems);
      exact_sol.attach_exact_value(exact_solution);
      exact_sol.attach_exact_deriv(exact_derivative);
      exact_sol.attach_exact_hessian(exact_hessian);
  
      ExactSolution zero_sol(equation_systems);
      zero_sol.attach_exact_value(zero_solution);
      zero_sol.attach_exact_deriv(zero_derivative);
      zero_sol.attach_exact_hessian(zero_hessian);
  
      LinearImplicitSystem&amp; system = 
        equation_systems.get_system&lt;LinearImplicitSystem&gt;(<FONT COLOR="#BC8F8F"><B>&quot;Biharmonic&quot;</FONT></B>);
  
      <B><FONT COLOR="#A020F0">for</FONT></B> (<FONT COLOR="#228B22"><B>unsigned</FONT></B> <FONT COLOR="#228B22"><B>int</FONT></B> r_step=0; r_step&lt;max_r_steps; r_step++)
        {
          mesh.print_info();
          equation_systems.print_info();
  
  	std::cout &lt;&lt; <FONT COLOR="#BC8F8F"><B>&quot;Beginning Solve &quot;</FONT></B> &lt;&lt; r_step &lt;&lt; std::endl;
  	
  	system.solve();
  
  	std::cout &lt;&lt; <FONT COLOR="#BC8F8F"><B>&quot;Linear solver converged at step: &quot;</FONT></B>
  		  &lt;&lt; system.n_linear_iterations()
  		  &lt;&lt; <FONT COLOR="#BC8F8F"><B>&quot;, final residual: &quot;</FONT></B>
  		  &lt;&lt; system.final_linear_residual()
  		  &lt;&lt; std::endl;
  
  	exact_sol.compute_error(<FONT COLOR="#BC8F8F"><B>&quot;Biharmonic&quot;</FONT></B>, <FONT COLOR="#BC8F8F"><B>&quot;u&quot;</FONT></B>);
  	zero_sol.compute_error(<FONT COLOR="#BC8F8F"><B>&quot;Biharmonic&quot;</FONT></B>, <FONT COLOR="#BC8F8F"><B>&quot;u&quot;</FONT></B>);
  
  	std::cout &lt;&lt; <FONT COLOR="#BC8F8F"><B>&quot;L2-Norm is: &quot;</FONT></B>
  		  &lt;&lt; zero_sol.l2_error(<FONT COLOR="#BC8F8F"><B>&quot;Biharmonic&quot;</FONT></B>, <FONT COLOR="#BC8F8F"><B>&quot;u&quot;</FONT></B>)
  		  &lt;&lt; std::endl;
  	std::cout &lt;&lt; <FONT COLOR="#BC8F8F"><B>&quot;H1-Norm is: &quot;</FONT></B>
  		  &lt;&lt; zero_sol.h1_error(<FONT COLOR="#BC8F8F"><B>&quot;Biharmonic&quot;</FONT></B>, <FONT COLOR="#BC8F8F"><B>&quot;u&quot;</FONT></B>)
  		  &lt;&lt; std::endl;
  	std::cout &lt;&lt; <FONT COLOR="#BC8F8F"><B>&quot;H2-Norm is: &quot;</FONT></B>
  		  &lt;&lt; zero_sol.h2_error(<FONT COLOR="#BC8F8F"><B>&quot;Biharmonic&quot;</FONT></B>, <FONT COLOR="#BC8F8F"><B>&quot;u&quot;</FONT></B>)
  		  &lt;&lt; std::endl
  		  &lt;&lt; std::endl;
  	std::cout &lt;&lt; <FONT COLOR="#BC8F8F"><B>&quot;L2-Error is: &quot;</FONT></B>
  		  &lt;&lt; exact_sol.l2_error(<FONT COLOR="#BC8F8F"><B>&quot;Biharmonic&quot;</FONT></B>, <FONT COLOR="#BC8F8F"><B>&quot;u&quot;</FONT></B>)
  		  &lt;&lt; std::endl;
  	std::cout &lt;&lt; <FONT COLOR="#BC8F8F"><B>&quot;H1-Error is: &quot;</FONT></B>
  		  &lt;&lt; exact_sol.h1_error(<FONT COLOR="#BC8F8F"><B>&quot;Biharmonic&quot;</FONT></B>, <FONT COLOR="#BC8F8F"><B>&quot;u&quot;</FONT></B>)
  		  &lt;&lt; std::endl;
  	std::cout &lt;&lt; <FONT COLOR="#BC8F8F"><B>&quot;H2-Error is: &quot;</FONT></B>
  		  &lt;&lt; exact_sol.h2_error(<FONT COLOR="#BC8F8F"><B>&quot;Biharmonic&quot;</FONT></B>, <FONT COLOR="#BC8F8F"><B>&quot;u&quot;</FONT></B>)
  		  &lt;&lt; std::endl
  		  &lt;&lt; std::endl;
  
  	out &lt;&lt; equation_systems.n_active_dofs() &lt;&lt; <FONT COLOR="#BC8F8F"><B>&quot; &quot;</FONT></B>
  	    &lt;&lt; exact_sol.l2_error(<FONT COLOR="#BC8F8F"><B>&quot;Biharmonic&quot;</FONT></B>, <FONT COLOR="#BC8F8F"><B>&quot;u&quot;</FONT></B>) &lt;&lt; <FONT COLOR="#BC8F8F"><B>&quot; &quot;</FONT></B>
  	    &lt;&lt; exact_sol.h1_error(<FONT COLOR="#BC8F8F"><B>&quot;Biharmonic&quot;</FONT></B>, <FONT COLOR="#BC8F8F"><B>&quot;u&quot;</FONT></B>) &lt;&lt; <FONT COLOR="#BC8F8F"><B>&quot; &quot;</FONT></B>
  	    &lt;&lt; exact_sol.h2_error(<FONT COLOR="#BC8F8F"><B>&quot;Biharmonic&quot;</FONT></B>, <FONT COLOR="#BC8F8F"><B>&quot;u&quot;</FONT></B>) &lt;&lt; std::endl;
  
  	<B><FONT COLOR="#A020F0">if</FONT></B> (r_step+1 != max_r_steps)
  	  {
  	    std::cout &lt;&lt; <FONT COLOR="#BC8F8F"><B>&quot;  Refining the mesh...&quot;</FONT></B> &lt;&lt; std::endl;
  
  	    <B><FONT COLOR="#A020F0">if</FONT></B> (uniform_refine == 0)
  	      {
  		ErrorVector error;
  		LaplacianErrorEstimator error_estimator;
  
  		error_estimator.estimate_error(system, error);
                  mesh_refinement.flag_elements_by_elem_fraction
  				(error, refine_percentage,
  				 coarsen_percentage, max_r_level);
  
  		std::cerr &lt;&lt; <FONT COLOR="#BC8F8F"><B>&quot;Mean Error: &quot;</FONT></B> &lt;&lt; error.mean() &lt;&lt;
  				std::endl;
  		std::cerr &lt;&lt; <FONT COLOR="#BC8F8F"><B>&quot;Error Variance: &quot;</FONT></B> &lt;&lt; error.variance() &lt;&lt;
  				std::endl;
  
  		mesh_refinement.refine_and_coarsen_elements();
                }
  	    <B><FONT COLOR="#A020F0">else</FONT></B>
  	      {
                  mesh_refinement.uniformly_refine(1);
                }
  		
  	    equation_systems.reinit ();
  	  }
        }	    
      
      GMVIO (mesh).write_equation_systems (gmv_file,
      					 equation_systems);
      out &lt;&lt; <FONT COLOR="#BC8F8F"><B>&quot;];\n&quot;</FONT></B>
  	&lt;&lt; <FONT COLOR="#BC8F8F"><B>&quot;hold on\n&quot;</FONT></B>
  	&lt;&lt; <FONT COLOR="#BC8F8F"><B>&quot;loglog(e(:,1), e(:,2), 'bo-');\n&quot;</FONT></B>
  	&lt;&lt; <FONT COLOR="#BC8F8F"><B>&quot;loglog(e(:,1), e(:,3), 'ro-');\n&quot;</FONT></B>
  	&lt;&lt; <FONT COLOR="#BC8F8F"><B>&quot;loglog(e(:,1), e(:,4), 'go-');\n&quot;</FONT></B>
  	&lt;&lt; <FONT COLOR="#BC8F8F"><B>&quot;xlabel('log(dofs)');\n&quot;</FONT></B>
  	&lt;&lt; <FONT COLOR="#BC8F8F"><B>&quot;ylabel('log(error)');\n&quot;</FONT></B>
  	&lt;&lt; <FONT COLOR="#BC8F8F"><B>&quot;title('C1 &quot;</FONT></B> &lt;&lt; approx_type &lt;&lt; <FONT COLOR="#BC8F8F"><B>&quot; elements');\n&quot;</FONT></B>
  	&lt;&lt; <FONT COLOR="#BC8F8F"><B>&quot;legend('L2-error', 'H1-error', 'H2-error');\n&quot;</FONT></B>;
    }
    
    <B><FONT COLOR="#A020F0">return</FONT></B> libMesh::close ();
  #endif
  }
  
  
  
  Number exact_2D_solution(<FONT COLOR="#228B22"><B>const</FONT></B> Point&amp; p,
  		         <FONT COLOR="#228B22"><B>const</FONT></B> Parameters&amp;,  <I><FONT COLOR="#B22222">// parameters, not needed
</FONT></I>  		         <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;, <I><FONT COLOR="#B22222">// sys_name, not needed
</FONT></I>  		         <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;) <I><FONT COLOR="#B22222">// unk_name, not needed
</FONT></I>  {
    <FONT COLOR="#228B22"><B>const</FONT></B> Real x = p(0);
    <FONT COLOR="#228B22"><B>const</FONT></B> Real y = p(1);
  
    <B><FONT COLOR="#A020F0">return</FONT></B> 256.*(x-x*x)*(x-x*x)*(y-y*y)*(y-y*y);
  }
  
  
  Gradient exact_2D_derivative(<FONT COLOR="#228B22"><B>const</FONT></B> Point&amp; p,
  			     <FONT COLOR="#228B22"><B>const</FONT></B> Parameters&amp;,  <I><FONT COLOR="#B22222">// parameters, not needed
</FONT></I>  			     <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;, <I><FONT COLOR="#B22222">// sys_name, not needed
</FONT></I>  			     <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;) <I><FONT COLOR="#B22222">// unk_name, not needed
</FONT></I>  {
    <FONT COLOR="#228B22"><B>const</FONT></B> Real x = p(0);
    <FONT COLOR="#228B22"><B>const</FONT></B> Real y = p(1);
  
    Gradient gradu;
  
    gradu(0) = 256.*2.*(x-x*x)*(1-2*x)*(y-y*y)*(y-y*y);
    gradu(1) = 256.*2.*(x-x*x)*(x-x*x)*(y-y*y)*(1-2*y);
  
    <B><FONT COLOR="#A020F0">return</FONT></B> gradu;
  }
  
  
  Tensor exact_2D_hessian(<FONT COLOR="#228B22"><B>const</FONT></B> Point&amp; p,
  			<FONT COLOR="#228B22"><B>const</FONT></B> Parameters&amp;,  <I><FONT COLOR="#B22222">// parameters, not needed
</FONT></I>  			<FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;, <I><FONT COLOR="#B22222">// sys_name, not needed
</FONT></I>  			<FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;) <I><FONT COLOR="#B22222">// unk_name, not needed
</FONT></I>  {
    Tensor hessu;
    
    <FONT COLOR="#228B22"><B>const</FONT></B> Real x = p(0);
    <FONT COLOR="#228B22"><B>const</FONT></B> Real y = p(1);
  
    hessu(0,0) = 256.*2.*(1-6.*x+6.*x*x)*(y-y*y)*(y-y*y);
    hessu(0,1) = 256.*4.*(x-x*x)*(1.-2.*x)*(y-y*y)*(1.-2.*y);
    hessu(1,1) = 256.*2.*(x-x*x)*(x-x*x)*(1.-6.*y+6.*y*y);
  
    hessu(1,0) = hessu(0,1);
    <B><FONT COLOR="#A020F0">return</FONT></B> hessu;
  }
  
  
  
  Number forcing_function_2D(<FONT COLOR="#228B22"><B>const</FONT></B> Point&amp; p)
  {
    <FONT COLOR="#228B22"><B>const</FONT></B> Real x = p(0);
    <FONT COLOR="#228B22"><B>const</FONT></B> Real y = p(1);
  
    <B><FONT COLOR="#A020F0">return</FONT></B> 256. * 8. * (3.*((y-y*y)*(y-y*y)+(x-x*x)*(x-x*x))
           + (1.-6.*x+6.*x*x)*(1.-6.*y+6.*y*y));
  }
  
  
  
  Number exact_3D_solution(<FONT COLOR="#228B22"><B>const</FONT></B> Point&amp; p,
  		         <FONT COLOR="#228B22"><B>const</FONT></B> Parameters&amp;,  <I><FONT COLOR="#B22222">// parameters, not needed
</FONT></I>  		         <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;, <I><FONT COLOR="#B22222">// sys_name, not needed
</FONT></I>  		         <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;) <I><FONT COLOR="#B22222">// unk_name, not needed
</FONT></I>  {
    <FONT COLOR="#228B22"><B>const</FONT></B> Real x = p(0);
    <FONT COLOR="#228B22"><B>const</FONT></B> Real y = p(1);
    <FONT COLOR="#228B22"><B>const</FONT></B> Real z = p(2);
    
    <B><FONT COLOR="#A020F0">return</FONT></B> 4096.*(x-x*x)*(x-x*x)*(y-y*y)*(y-y*y)*(z-z*z)*(z-z*z);
  }
  
  
  Gradient exact_3D_derivative(<FONT COLOR="#228B22"><B>const</FONT></B> Point&amp; p,
  			     <FONT COLOR="#228B22"><B>const</FONT></B> Parameters&amp;,  <I><FONT COLOR="#B22222">// parameters, not needed
</FONT></I>  			     <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;, <I><FONT COLOR="#B22222">// sys_name, not needed
</FONT></I>  			     <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;) <I><FONT COLOR="#B22222">// unk_name, not needed
</FONT></I>  {
    Gradient gradu;
    
    <FONT COLOR="#228B22"><B>const</FONT></B> Real x = p(0);
    <FONT COLOR="#228B22"><B>const</FONT></B> Real y = p(1);
    <FONT COLOR="#228B22"><B>const</FONT></B> Real z = p(2);
  
    gradu(0) = 4096.*2.*(x-x*x)*(1.-2.*x)*(y-y*y)*(y-y*y)*(z-z*z)*(z-z*z);
    gradu(1) = 4096.*2.*(x-x*x)*(x-x*x)*(y-y*y)*(1.-2.*y)*(z-z*z)*(z-z*z);
    gradu(2) = 4096.*2.*(x-x*x)*(x-x*x)*(y-y*y)*(y-y*y)*(z-z*z)*(1.-2.*z);
  
    <B><FONT COLOR="#A020F0">return</FONT></B> gradu;
  }
  
  
  Tensor exact_3D_hessian(<FONT COLOR="#228B22"><B>const</FONT></B> Point&amp; p,
  			<FONT COLOR="#228B22"><B>const</FONT></B> Parameters&amp;,  <I><FONT COLOR="#B22222">// parameters, not needed
</FONT></I>  			<FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;, <I><FONT COLOR="#B22222">// sys_name, not needed
</FONT></I>  			<FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp;) <I><FONT COLOR="#B22222">// unk_name, not needed
</FONT></I>  {
    Tensor hessu;
    
    <FONT COLOR="#228B22"><B>const</FONT></B> Real x = p(0);
    <FONT COLOR="#228B22"><B>const</FONT></B> Real y = p(1);
    <FONT COLOR="#228B22"><B>const</FONT></B> Real z = p(2);
  
    hessu(0,0) = 4096.*(2.-12.*x+12.*x*x)*(y-y*y)*(y-y*y)*(z-z*z)*(z-z*z);
    hessu(0,1) = 4096.*4.*(x-x*x)*(1.-2.*x)*(y-y*y)*(1.-2.*y)*(z-z*z)*(z-z*z);
    hessu(0,2) = 4096.*4.*(x-x*x)*(1.-2.*x)*(y-y*y)*(y-y*y)*(z-z*z)*(1.-2.*z);
    hessu(1,1) = 4096.*(x-x*x)*(x-x*x)*(2.-12.*y+12.*y*y)*(z-z*z)*(z-z*z);
    hessu(1,2) = 4096.*4.*(x-x*x)*(x-x*x)*(y-y*y)*(1.-2.*y)*(z-z*z)*(1.-2.*z);
    hessu(2,2) = 4096.*(x-x*x)*(x-x*x)*(y-y*y)*(y-y*y)*(2.-12.*z+12.*z*z);
  
    hessu(1,0) = hessu(0,1);
    hessu(2,0) = hessu(0,2);
    hessu(2,1) = hessu(1,2);
  
    <B><FONT COLOR="#A020F0">return</FONT></B> hessu;
  }
  
  
  
  Number forcing_function_3D(<FONT COLOR="#228B22"><B>const</FONT></B> Point&amp; p)
  {
    <FONT COLOR="#228B22"><B>const</FONT></B> Real x = p(0);
    <FONT COLOR="#228B22"><B>const</FONT></B> Real y = p(1);
    <FONT COLOR="#228B22"><B>const</FONT></B> Real z = p(2);
  
    <B><FONT COLOR="#A020F0">return</FONT></B> 4096. * 8. * (3.*((y-y*y)*(y-y*y)*(x-x*x)*(x-x*x) +
                             (z-z*z)*(z-z*z)*(x-x*x)*(x-x*x) +
                             (z-z*z)*(z-z*z)*(y-y*y)*(y-y*y)) +
           (1.-6.*x+6.*x*x)*(1.-6.*y+6.*y*y)*(z-z*z)*(z-z*z) +
           (1.-6.*x+6.*x*x)*(1.-6.*z+6.*z*z)*(y-y*y)*(y-y*y) +
           (1.-6.*y+6.*y*y)*(1.-6.*z+6.*z*z)*(x-x*x)*(x-x*x));
  }
  
  
  
  <FONT COLOR="#228B22"><B>void</FONT></B> assemble_biharmonic(EquationSystems&amp; es,
                        <FONT COLOR="#228B22"><B>const</FONT></B> std::string&amp; system_name)
  {
    assert (system_name == <FONT COLOR="#BC8F8F"><B>&quot;Biharmonic&quot;</FONT></B>);
  
  #ifdef ENABLE_SECOND_DERIVATIVES
  
    PerfLog perf_log (<FONT COLOR="#BC8F8F"><B>&quot;Matrix Assembly&quot;</FONT></B>,false);
    
    <FONT COLOR="#228B22"><B>const</FONT></B> Mesh&amp; mesh = es.get_mesh();
  
    <FONT COLOR="#228B22"><B>const</FONT></B> <FONT COLOR="#228B22"><B>unsigned</FONT></B> <FONT COLOR="#228B22"><B>int</FONT></B> dim = mesh.mesh_dimension();
  
    LinearImplicitSystem&amp; system = es.get_system&lt;LinearImplicitSystem&gt;(<FONT COLOR="#BC8F8F"><B>&quot;Biharmonic&quot;</FONT></B>);
    
    <FONT COLOR="#228B22"><B>const</FONT></B> DofMap&amp; dof_map = system.get_dof_map();
  
    FEType fe_type = dof_map.variable_type(0);
  
    AutoPtr&lt;FEBase&gt; fe (FEBase::build(dim, fe_type));
    
    AutoPtr&lt;QBase&gt; qrule(fe_type.default_quadrature_rule(dim));
  
    fe-&gt;attach_quadrature_rule (qrule.get());
  
    AutoPtr&lt;FEBase&gt; fe_face (FEBase::build(dim, fe_type));
  	      
    AutoPtr&lt;QBase&gt; qface(fe_type.default_quadrature_rule(dim-1));
  
    fe_face-&gt;attach_quadrature_rule (qface.get());
  
    <FONT COLOR="#228B22"><B>const</FONT></B> std::vector&lt;Real&gt;&amp; JxW = fe-&gt;get_JxW();
  
    <FONT COLOR="#228B22"><B>const</FONT></B> std::vector&lt;Point&gt;&amp; q_point = fe-&gt;get_xyz();
  
    <FONT COLOR="#228B22"><B>const</FONT></B> std::vector&lt;std::vector&lt;Real&gt; &gt;&amp; phi = fe-&gt;get_phi();
  
    <FONT COLOR="#228B22"><B>const</FONT></B> std::vector&lt;std::vector&lt;RealTensor&gt; &gt;&amp; d2phi = fe-&gt;get_d2phi();
  
    std::vector&lt;Real&gt; shape_laplacian;
  
    DenseMatrix&lt;Number&gt; Ke;
    DenseVector&lt;Number&gt; Fe;
  
    std::vector&lt;<FONT COLOR="#228B22"><B>unsigned</FONT></B> <FONT COLOR="#228B22"><B>int</FONT></B>&gt; dof_indices;
  
  
    MeshBase::const_element_iterator       el     = mesh.active_local_elements_begin();
    <FONT COLOR="#228B22"><B>const</FONT></B> MeshBase::const_element_iterator end_el = mesh.active_local_elements_end(); 
    
    <B><FONT COLOR="#A020F0">for</FONT></B> ( ; el != end_el; ++el)
      {
        perf_log.start_event(<FONT COLOR="#BC8F8F"><B>&quot;elem init&quot;</FONT></B>);      
  
        <FONT COLOR="#228B22"><B>const</FONT></B> Elem* elem = *el;
  
        dof_map.dof_indices (elem, dof_indices);
  
        fe-&gt;reinit (elem);
  
        Ke.resize (dof_indices.size(),
  		 dof_indices.size());
  
        Fe.resize (dof_indices.size());
  
        shape_laplacian.resize(dof_indices.size());
  
        perf_log.stop_event(<FONT COLOR="#BC8F8F"><B>&quot;elem init&quot;</FONT></B>);      
  
        perf_log.start_event (<FONT COLOR="#BC8F8F"><B>&quot;Ke&quot;</FONT></B>);
  
        <B><FONT COLOR="#A020F0">for</FONT></B> (<FONT COLOR="#228B22"><B>unsigned</FONT></B> <FONT COLOR="#228B22"><B>int</FONT></B> qp=0; qp&lt;qrule-&gt;n_points(); qp++)
          {
  	  <B><FONT COLOR="#A020F0">for</FONT></B> (<FONT COLOR="#228B22"><B>unsigned</FONT></B> <FONT COLOR="#228B22"><B>int</FONT></B> i=0; i&lt;phi.size(); i++)
              {
                shape_laplacian[i] = d2phi[i][qp](0,0)+d2phi[i][qp](1,1);
                <B><FONT COLOR="#A020F0">if</FONT></B> (dim == 3)
                   shape_laplacian[i] += d2phi[i][qp](2,2);
              }
  	  <B><FONT COLOR="#A020F0">for</FONT></B> (<FONT COLOR="#228B22"><B>unsigned</FONT></B> <FONT COLOR="#228B22"><B>int</FONT></B> i=0; i&lt;phi.size(); i++)
  	    <B><FONT COLOR="#A020F0">for</FONT></B> (<FONT COLOR="#228B22"><B>unsigned</FONT></B> <FONT COLOR="#228B22"><B>int</FONT></B> j=0; j&lt;phi.size(); j++)
  	      Ke(i,j) += JxW[qp]*
                           shape_laplacian[i]*shape_laplacian[j];
          }
  
        perf_log.stop_event (<FONT COLOR="#BC8F8F"><B>&quot;Ke&quot;</FONT></B>);
  
  
        {
  	perf_log.start_event (<FONT COLOR="#BC8F8F"><B>&quot;BCs&quot;</FONT></B>);
  
  	<FONT COLOR="#228B22"><B>const</FONT></B> Real penalty = 1e10;
  	<FONT COLOR="#228B22"><B>const</FONT></B> Real penalty2 = 1e10;
  
  	<B><FONT COLOR="#A020F0">for</FONT></B> (<FONT COLOR="#228B22"><B>unsigned</FONT></B> <FONT COLOR="#228B22"><B>int</FONT></B> s=0; s&lt;elem-&gt;n_sides(); s++)
  	  <B><FONT COLOR="#A020F0">if</FONT></B> (elem-&gt;neighbor(s) == NULL)
  	    {
  	      <FONT COLOR="#228B22"><B>const</FONT></B> std::vector&lt;std::vector&lt;Real&gt; &gt;&amp;  phi_face =
  			      fe_face-&gt;get_phi();
  
                <FONT COLOR="#228B22"><B>const</FONT></B> std::vector&lt;std::vector&lt;RealGradient&gt; &gt;&amp; dphi_face =
  			      fe_face-&gt;get_dphi();
  
                <FONT COLOR="#228B22"><B>const</FONT></B> std::vector&lt;Real&gt;&amp; JxW_face = fe_face-&gt;get_JxW();
                                                                                 
                <FONT COLOR="#228B22"><B>const</FONT></B> std::vector&lt;Point &gt;&amp; qface_point = fe_face-&gt;get_xyz();
  
  	      <FONT COLOR="#228B22"><B>const</FONT></B> std::vector&lt;Point&gt;&amp; face_normals =
  			      fe_face-&gt;get_normals();
  
                fe_face-&gt;reinit(elem, s);
                                                                                  
                <B><FONT COLOR="#A020F0">for</FONT></B> (<FONT COLOR="#228B22"><B>unsigned</FONT></B> <FONT COLOR="#228B22"><B>int</FONT></B> qp=0; qp&lt;qface-&gt;n_points(); qp++)
                  {
  		  Number value = exact_solution(qface_point[qp],
  					        es.parameters, <FONT COLOR="#BC8F8F"><B>&quot;null&quot;</FONT></B>,
  					        <FONT COLOR="#BC8F8F"><B>&quot;void&quot;</FONT></B>);
  		  Gradient flux = exact_2D_derivative(qface_point[qp],
                                                        es.parameters,
  						      <FONT COLOR="#BC8F8F"><B>&quot;null&quot;</FONT></B>, <FONT COLOR="#BC8F8F"><B>&quot;void&quot;</FONT></B>);
  
                    <B><FONT COLOR="#A020F0">for</FONT></B> (<FONT COLOR="#228B22"><B>unsigned</FONT></B> <FONT COLOR="#228B22"><B>int</FONT></B> i=0; i&lt;phi_face.size(); i++)
                      <B><FONT COLOR="#A020F0">for</FONT></B> (<FONT COLOR="#228B22"><B>unsigned</FONT></B> <FONT COLOR="#228B22"><B>int</FONT></B> j=0; j&lt;phi_face.size(); j++)
  		      Ke(i,j) += JxW_face[qp] *
  				 (penalty * phi_face[i][qp] *
  				  phi_face[j][qp] + penalty2
  				  * (dphi_face[i][qp] *
  				  face_normals[qp]) *
  				  (dphi_face[j][qp] *
  				   face_normals[qp]));
  
                    <B><FONT COLOR="#A020F0">for</FONT></B> (<FONT COLOR="#228B22"><B>unsigned</FONT></B> <FONT COLOR="#228B22"><B>int</FONT></B> i=0; i&lt;phi_face.size(); i++)
                      Fe(i) += JxW_face[qp] *
  				    (penalty * value * phi_face[i][qp]
  				     + penalty2 * 
  				     (flux * face_normals[qp])
  				    * (dphi_face[i][qp]
  				       * face_normals[qp]));
  
                  }
  	    } 
  	
  	perf_log.stop_event (<FONT COLOR="#BC8F8F"><B>&quot;BCs&quot;</FONT></B>);
        } 
  
        <B><FONT COLOR="#A020F0">for</FONT></B> (<FONT COLOR="#228B22"><B>unsigned</FONT></B> <FONT COLOR="#228B22"><B>int</FONT></B> qp=0; qp&lt;qrule-&gt;n_points(); qp++)
  	<B><FONT COLOR="#A020F0">for</FONT></B> (<FONT COLOR="#228B22"><B>unsigned</FONT></B> <FONT COLOR="#228B22"><B>int</FONT></B> i=0; i&lt;phi.size(); i++)
  	  Fe(i) += JxW[qp]*phi[i][qp]*forcing_function(q_point[qp]);
  
        perf_log.start_event (<FONT COLOR="#BC8F8F"><B>&quot;matrix insertion&quot;</FONT></B>);
  
        dof_map.constrain_element_matrix_and_vector(Ke, Fe, dof_indices);
        system.matrix-&gt;add_matrix (Ke, dof_indices);
        system.rhs-&gt;add_vector    (Fe, dof_indices);
  
        perf_log.stop_event (<FONT COLOR="#BC8F8F"><B>&quot;matrix insertion&quot;</FONT></B>);
      }
  
  
  #<B><FONT COLOR="#A020F0">else</FONT></B>
  
  #endif
  
  }
</pre> 
<a name="output"></a> 
<br><br><br> <h1> The console output of the program: </h1> 
<pre>
Compiling C++ (in debug mode) ex15.C...
Linking ex15...
/local/libmesh/contrib/tecplot/lib/i686-pc-linux-gnu/tecio.a(tecxxx.o)(.text+0x1a7): In function `tecini':
: warning: the use of `mktemp' is dangerous, better use `mkstemp'
***************************************************************
* Running Example  ./ex15 -ksp_type bcgs -pc_type jacobi
***************************************************************
 
 EquationSystems
  n_systems()=1
   System "Biharmonic"
    Type "LinearImplicit"
    Variables="u" 
    Finite Element Types="HERMITE", "JACOBI_20_00" 
    Infinite Element Mapping="CARTESIAN" 
    Approximation Orders="THIRD", "THIRD" 
    n_dofs()=16
    n_local_dofs()=16
    n_constrained_dofs()=0
    n_vectors()=1

 Mesh Information:
  mesh_dimension()=2
  spatial_dimension()=3
  n_nodes()=9
  n_elem()=1
   n_local_elem()=1
   n_active_elem()=1
  n_subdomains()=1
  n_processors()=1
  processor_id()=0

 EquationSystems
  n_systems()=1
   System "Biharmonic"
    Type "LinearImplicit"
    Variables="u" 
    Finite Element Types="HERMITE", "JACOBI_20_00" 
    Infinite Element Mapping="CARTESIAN" 
    Approximation Orders="THIRD", "THIRD" 
    n_dofs()=16
    n_local_dofs()=16
    n_constrained_dofs()=0
    n_vectors()=1

Beginning Solve 0
Linear solver converged at step: 11, final residual: 1.26966e-24
L2-Norm is: 1.06079e-08
H1-Norm is: 1.07761e-08
H2-Norm is: 1.35257e-08

L2-Error is: 0.400544
H1-Error is: 2.0166
H2-Error is: 14.6862

  Refining the mesh...
 Mesh Information:
  mesh_dimension()=2
  spatial_dimension()=3
  n_nodes()=25
  n_elem()=5
   n_local_elem()=5
   n_active_elem()=4
  n_subdomains()=1
  n_processors()=1
  processor_id()=0

 EquationSystems
  n_systems()=1
   System "Biharmonic"
    Type "LinearImplicit"
    Variables="u" 
    Finite Element Types="HERMITE", "JACOBI_20_00" 
    Infinite Element Mapping="CARTESIAN" 
    Approximation Orders="THIRD", "THIRD" 
    n_dofs()=36
    n_local_dofs()=36
    n_constrained_dofs()=0
    n_vectors()=1

Beginning Solve 1
Linear solver converged at step: 7, final residual: 5.94714e-19
L2-Norm is: 0.384025
H1-Norm is: 1.98976
H2-Norm is: 14.3417

L2-Error is: 0.0335358
H1-Error is: 0.267039
H2-Error is: 3.51162

  Refining the mesh...
 Mesh Information:
  mesh_dimension()=2
  spatial_dimension()=3
  n_nodes()=81
  n_elem()=21
   n_local_elem()=21
   n_active_elem()=16
  n_subdomains()=1
  n_processors()=1
  processor_id()=0

 EquationSystems
  n_systems()=1
   System "Biharmonic"
    Type "LinearImplicit"
    Variables="u" 
    Finite Element Types="HERMITE", "JACOBI_20_00" 
    Infinite Element Mapping="CARTESIAN" 
    Approximation Orders="THIRD", "THIRD" 
    n_dofs()=100
    n_local_dofs()=100
    n_constrained_dofs()=0
    n_vectors()=1

Beginning Solve 2
Linear solver converged at step: 24, final residual: 2.36986e-19
L2-Norm is: 0.404988
H1-Norm is: 2.02995
H2-Norm is: 14.7459

L2-Error is: 0.0020746
H1-Error is: 0.0316727
H2-Error is: 0.822125

  Refining the mesh...
 Mesh Information:
  mesh_dimension()=2
  spatial_dimension()=3
  n_nodes()=289
  n_elem()=85
   n_local_elem()=85
   n_active_elem()=64
  n_subdomains()=1
  n_processors()=1
  processor_id()=0

 EquationSystems
  n_systems()=1
   System "Biharmonic"
    Type "LinearImplicit"
    Variables="u" 
    Finite Element Types="HERMITE", "JACOBI_20_00" 
    Infinite Element Mapping="CARTESIAN" 
    Approximation Orders="THIRD", "THIRD" 
    n_dofs()=324
    n_local_dofs()=324
    n_constrained_dofs()=0
    n_vectors()=1

Beginning Solve 3
Linear solver converged at step: 51, final residual: 2.93363e-19
L2-Norm is: 0.406264
H1-Norm is: 2.03164
H2-Norm is: 14.7676

L2-Error is: 0.000129445
H1-Error is: 0.00390589
H2-Error is: 0.202531

  Refining the mesh...
 Mesh Information:
  mesh_dimension()=2
  spatial_dimension()=3
  n_nodes()=1089
  n_elem()=341
   n_local_elem()=341
   n_active_elem()=256
  n_subdomains()=1
  n_processors()=1
  processor_id()=0

 EquationSystems
  n_systems()=1
   System "Biharmonic"
    Type "LinearImplicit"
    Variables="u" 
    Finite Element Types="HERMITE", "JACOBI_20_00" 
    Infinite Element Mapping="CARTESIAN" 
    Approximation Orders="THIRD", "THIRD" 
    n_dofs()=1156
    n_local_dofs()=1156
    n_constrained_dofs()=0
    n_vectors()=1

Beginning Solve 4
Linear solver converged at step: 170, final residual: 1.4037e-20
L2-Norm is: 0.406344
H1-Norm is: 2.03174
H2-Norm is: 14.7689

L2-Error is: 8.07721e-06
H1-Error is: 0.000486566
H2-Error is: 0.050454


 ---------------------------------------------------------------------------- 
| Reference count information                                                |
 ---------------------------------------------------------------------------- 
| 12LinearSolverIdE reference count information:
|  Creations:    1
|  Destructions: 1
| 12SparseMatrixIdE reference count information:
|  Creations:    1
|  Destructions: 1
| 13NumericVectorIdE reference count information:
|  Creations:    7
|  Destructions: 7
| 4Elem reference count information:
|  Creations:    2058
|  Destructions: 2058
| 4Node reference count information:
|  Creations:    1089
|  Destructions: 1089
| 5QBase reference count information:
|  Creations:    392
|  Destructions: 392
| 6DofMap reference count information:
|  Creations:    1
|  Destructions: 1
| 6FEBase reference count information:
|  Creations:    710
|  Destructions: 710
| 6System reference count information:
|  Creations:    1
|  Destructions: 1
| 9DofObject reference count information:
|  Creations:    3673
|  Destructions: 3673
| N10Parameters5ValueE reference count information:
|  Creations:    2
|  Destructions: 2
 ---------------------------------------------------------------------------- 
 
***************************************************************
* Done Running Example  ./ex15
***************************************************************
</pre>
</div>
<?php make_footer() ?>
</body>
</html>
<?php if (0) { ?>
\#Local Variables:
\#mode: html
\#End:
<?php } ?>
